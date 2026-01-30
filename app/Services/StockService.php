<?php

namespace App\Services;

use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Stock In - Nhập hàng vào kho
     */
    public function stockIn(array $data): StockIn
    {
        return DB::transaction(function () use ($data) {
            // Create stock in record
            $stockIn = StockIn::create($data);

            // Update inventory
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                ],
                ['quantity' => 0]
            );

            $inventory->increment('quantity', $data['quantity']);

            return $stockIn->load(['product', 'warehouse']);
        });
    }

    /**
     * Stock Out - Xuất hàng ra khỏi kho
     */
    public function stockOut(array $data): StockOut
    {
        return DB::transaction(function () use ($data) {
            // Check inventory
            $inventory = Inventory::where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->first();

            if (!$inventory) {
                throw new \Exception('Sản phẩm không tồn tại trong kho này!');
            }

            if ($inventory->quantity < $data['quantity']) {
                throw new \Exception(
                    "Không đủ hàng trong kho! Tồn kho hiện tại: {$inventory->quantity}"
                );
            }

            // Create stock out record
            $stockOut = StockOut::create($data);

            // Update inventory
            $inventory->decrement('quantity', $data['quantity']);

            return $stockOut->load(['product', 'warehouse']);
        });
    }

    /**
     * Transfer stock between warehouses
     */
    public function transferStock(int $productId, int $fromWarehouseId, int $toWarehouseId, int $quantity, string $note = null)
    {
        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $note) {
            // Check source warehouse
            $fromInventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->first();

            if (!$fromInventory || $fromInventory->quantity < $quantity) {
                throw new \Exception('Kho nguồn không đủ hàng để chuyển!');
            }

            // Stock out from source warehouse
            StockOut::create([
                'product_id' => $productId,
                'warehouse_id' => $fromWarehouseId,
                'quantity' => $quantity,
                'export_date' => now(),
                'reason' => 'Transfer',
            ]);
            $fromInventory->decrement('quantity', $quantity);

            // Stock in to destination warehouse
            StockIn::create([
                'product_id' => $productId,
                'warehouse_id' => $toWarehouseId,
                'quantity' => $quantity,
                'import_date' => now(),
                'note' => $note ?? 'Transfer from warehouse',
            ]);

            $toInventory = Inventory::firstOrCreate(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $toWarehouseId,
                ],
                ['quantity' => 0]
            );
            $toInventory->increment('quantity', $quantity);

            return [
                'from_warehouse' => $fromInventory->fresh('warehouse'),
                'to_warehouse' => $toInventory->fresh('warehouse'),
                'quantity_transferred' => $quantity,
            ];
        });
    }

    /**
     * Get stock in history
     */
    public function getStockInHistory(array $filters = [])
    {
        $query = StockIn::query();

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('import_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('import_date', '<=', $filters['to_date']);
        }

        return $query->with(['product', 'warehouse'])
            ->orderBy('import_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get stock out history
     */
    public function getStockOutHistory(array $filters = [])
    {
        $query = StockOut::query();

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('export_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('export_date', '<=', $filters['to_date']);
        }

        return $query->with(['product', 'warehouse'])
            ->orderBy('export_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get inventory report by warehouse
     */
    public function getInventoryReport(int $warehouseId)
    {
        return Inventory::where('warehouse_id', $warehouseId)
            ->with(['product', 'warehouse'])
            ->get()
            ->map(function ($inventory) {
                return [
                    'product_id' => $inventory->product_id,
                    'product_sku' => $inventory->product->sku,
                    'product_name' => $inventory->product->name,
                    'quantity' => $inventory->quantity,
                    'min_stock' => $inventory->product->min_stock,
                    'status' => $inventory->quantity <= $inventory->product->min_stock ? 'Low Stock' : 'OK',
                ];
            });
    }

    /**
     * Get inventory movements (stock in/out) for a product
     */
    public function getProductMovements(int $productId, array $filters = [])
    {
        $stockIns = StockIn::where('product_id', $productId)
            ->when(!empty($filters['from_date']), function ($q) use ($filters) {
                $q->whereDate('import_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($q) use ($filters) {
                $q->whereDate('import_date', '<=', $filters['to_date']);
            })
            ->with('warehouse')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Stock In',
                    'date' => $item->import_date,
                    'warehouse' => $item->warehouse->name,
                    'quantity' => $item->quantity,
                    'note' => $item->note,
                ];
            });

        $stockOuts = StockOut::where('product_id', $productId)
            ->when(!empty($filters['from_date']), function ($q) use ($filters) {
                $q->whereDate('export_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($q) use ($filters) {
                $q->whereDate('export_date', '<=', $filters['to_date']);
            })
            ->with('warehouse')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Stock Out',
                    'date' => $item->export_date,
                    'warehouse' => $item->warehouse->name,
                    'quantity' => -$item->quantity,
                    'note' => $item->reason,
                ];
            });

        return $stockIns->concat($stockOuts)->sortByDesc('date')->values();
    }

    /**
     * Adjust inventory (manual correction)
     */
    public function adjustInventory(int $productId, int $warehouseId, int $newQuantity, string $reason)
    {
        return DB::transaction(function () use ($productId, $warehouseId, $newQuantity, $reason) {
            $inventory = Inventory::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->firstOrFail();

            $difference = $newQuantity - $inventory->quantity;

            if ($difference > 0) {
                // Stock in
                StockIn::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $difference,
                    'import_date' => now(),
                    'note' => 'Adjustment: ' . $reason,
                ]);
            } elseif ($difference < 0) {
                // Stock out
                StockOut::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => abs($difference),
                    'export_date' => now(),
                    'reason' => 'Adjustment: ' . $reason,
                ]);
            }

            $inventory->update(['quantity' => $newQuantity]);

            return $inventory->fresh(['product', 'warehouse']);
        });
    }
}
