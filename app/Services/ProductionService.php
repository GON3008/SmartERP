<?php

namespace App\Services;

use App\Models\ProductionOrder;
use App\Models\BillOfMaterial;
use App\Models\Inventory;
use App\Models\StockOut;
use App\Models\StockIn;
use App\Models\ProductionLog;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Get all production orders
     */
    public function getAllProductionOrders(array $filters = [])
    {
        $query = ProductionOrder::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('start_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('end_date', '<=', $filters['to_date']);
        }

        return $query->with(['product', 'logs'])
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get production order by ID
     */
    public function getProductionOrderById(int $id): ProductionOrder
    {
        return ProductionOrder::with(['product', 'logs'])->findOrFail($id);
    }

    /**
     * Create production order
     */
    public function createProductionOrder(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            // Check if product has BOM
            $hasBOM = BillOfMaterial::where('product_id', $data['product_id'])->exists();
            if (!$hasBOM) {
                throw new \Exception('Sản phẩm chưa có công thức sản xuất (BOM)!');
            }

            // Create production order
            $order = ProductionOrder::create($data);

            // Create initial log
            ProductionLog::create([
                'production_order_id' => $order->id,
                'note' => 'Lệnh sản xuất được tạo',
            ]);

            return $order->load('product');
        });
    }

    /**
     * Update production order
     */
    public function updateProductionOrder(int $id, array $data): ProductionOrder
    {
        return DB::transaction(function () use ($id, $data) {
            $order = ProductionOrder::findOrFail($id);

            // Prevent updating completed orders
            if ($order->status === 'completed') {
                throw new \Exception('Không thể cập nhật lệnh sản xuất đã hoàn thành!');
            }

            // Prevent updating cancelled orders
            if ($order->status === 'cancelled') {
                throw new \Exception('Không thể cập nhật lệnh sản xuất đã hủy!');
            }

            // If changing product, check BOM exists
            if (isset($data['product_id']) && $data['product_id'] !== $order->product_id) {
                $hasBOM = BillOfMaterial::where('product_id', $data['product_id'])->exists();
                if (!$hasBOM) {
                    throw new \Exception('Sản phẩm mới chưa có công thức sản xuất (BOM)!');
                }
            }

            // Prevent changing to in_progress without start_date
            if (isset($data['status']) && $data['status'] === 'in_progress') {
                if (empty($data['start_date']) && !$order->start_date) {
                    $data['start_date'] = now();
                }
            }

            // Prevent changing to completed without end_date
            if (isset($data['status']) && $data['status'] === 'completed') {
                if (empty($data['end_date'])) {
                    $data['end_date'] = now();
                }
            }

            // Update order
            $order->update($data);

            // Add log
            ProductionLog::create([
                'production_order_id' => $order->id,
                'note' => 'Lệnh sản xuất được cập nhật',
            ]);

            return $order->fresh(['product', 'logs']);
        });
    }

    /**
     * Start production (change status to in_progress)
     */
    public function startProduction(int $id, int $warehouseId): ProductionOrder
    {
        return DB::transaction(function () use ($id, $warehouseId) {
            $order = ProductionOrder::with('product')->findOrFail($id);

            if ($order->status !== 'pending') {
                throw new \Exception('Chỉ có thể bắt đầu lệnh sản xuất đang chờ!');
            }

            // Get BOM
            $materials = BillOfMaterial::where('product_id', $order->product_id)
                ->with('material')
                ->get();

            // Check material availability
            foreach ($materials as $bom) {
                $requiredQty = $bom->quantity_required * $order->quantity;
                $inventory = Inventory::where('product_id', $bom->material_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if (!$inventory || $inventory->quantity < $requiredQty) {
                    throw new \Exception(
                        "Không đủ nguyên liệu: {$bom->material->name}. " .
                            "Cần: {$requiredQty}, Có: " . ($inventory->quantity ?? 0)
                    );
                }
            }

            // Consume materials
            foreach ($materials as $bom) {
                $requiredQty = $bom->quantity_required * $order->quantity;

                // Update inventory
                $inventory = Inventory::where('product_id', $bom->material_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                $inventory->decrement('quantity', $requiredQty);

                // Create stock out record
                StockOut::create([
                    'product_id' => $bom->material_id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $requiredQty,
                    'export_date' => now(),
                    'reason' => 'Production - Order: ' . $order->order_code,
                ]);
            }

            // Update production order
            $order->update([
                'status' => 'in_progress',
                'start_date' => now(),
            ]);

            // Add log
            ProductionLog::create([
                'production_order_id' => $order->id,
                'note' => 'Bắt đầu sản xuất - Đã tiêu thụ nguyên liệu',
            ]);

            return $order->fresh(['product', 'logs']);
        });
    }

    /**
     * Complete production
     */
    public function completeProduction(int $id, int $warehouseId, ?int $actualQuantity = null): ProductionOrder
    {
        return DB::transaction(function () use ($id, $warehouseId, $actualQuantity) {
            $order = ProductionOrder::findOrFail($id);

            if ($order->status !== 'in_progress') {
                throw new \Exception('Chỉ có thể hoàn thành lệnh sản xuất đang xử lý!');
            }

            $producedQuantity = $actualQuantity ?? $order->quantity;

            // Add produced items to inventory
            $inventory = Inventory::firstOrCreate(
                [
                    'product_id' => $order->product_id,
                    'warehouse_id' => $warehouseId,
                ],
                ['quantity' => 0]
            );
            $inventory->increment('quantity', $producedQuantity);

            // Create stock in record
            StockIn::create([
                'product_id' => $order->product_id,
                'warehouse_id' => $warehouseId,
                'quantity' => $producedQuantity,
                'import_date' => now(),
                'note' => 'Production completed - Order: ' . $order->order_code,
            ]);

            // Update production order
            $order->update([
                'status' => 'completed',
                'end_date' => now(),
            ]);

            // Add log
            ProductionLog::create([
                'production_order_id' => $order->id,
                'note' => "Hoàn thành sản xuất - Số lượng: {$producedQuantity}",
            ]);

            return $order->fresh(['product', 'logs']);
        });
    }

    /**
     * Cancel production order
     */
    public function cancelProduction(int $id, ?string $reason = null): ProductionOrder
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status === 'completed') {
            throw new \Exception('Không thể hủy lệnh sản xuất đã hoàn thành!');
        }

        $order->update(['status' => 'cancelled']);

        // Add log
        ProductionLog::create([
            'production_order_id' => $order->id,
            'note' => 'Lệnh sản xuất bị hủy. Lý do: ' . ($reason ?? 'Không rõ'),
        ]);

        return $order->fresh(['product', 'logs']);
    }

    /**
     * Delete production order
     */
    public function deleteProductionOrder(int $id): bool
    {
        $order = ProductionOrder::findOrFail($id);

        // Only allow deleting pending or cancelled orders
        if (!in_array($order->status, ['pending', 'cancelled'])) {
            throw new \Exception('Chỉ có thể xóa lệnh sản xuất đang chờ hoặc đã hủy!');
        }

        return DB::transaction(function () use ($order) {
            $order->logs()->delete();
            return $order->delete();
        });
    }

    /**
     * Add production log
     */
    public function addProductionLog(int $productionOrderId, string $note): ProductionLog
    {
        return ProductionLog::create([
            'production_order_id' => $productionOrderId,
            'note' => $note,
        ]);
    }

    /**
     * Get material requirements for production
     */
    public function getMaterialRequirements(int $productId, int $quantity): array
    {
        $materials = BillOfMaterial::where('product_id', $productId)
            ->with('material')
            ->get();

        return $materials->map(function ($bom) use ($quantity) {
            $required = $bom->quantity_required * $quantity;

            return [
                'material_id' => $bom->material_id,
                'material_sku' => $bom->material->sku,
                'material_name' => $bom->material->name,
                'unit' => $bom->material->unit,
                'required_per_unit' => $bom->quantity_required,
                'total_required' => $required,
            ];
        })->toArray();
    }

    /**
     * Check if can produce (enough materials)
     */
    public function canProduce(int $productId, int $quantity, int $warehouseId): array
    {
        $materials = BillOfMaterial::where('product_id', $productId)
            ->with('material')
            ->get();

        $canProduce = true;
        $details = [];

        foreach ($materials as $bom) {
            $required = $bom->quantity_required * $quantity;
            $inventory = Inventory::where('product_id', $bom->material_id)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $available = $inventory ? $inventory->quantity : 0;
            $sufficient = $available >= $required;

            if (!$sufficient) {
                $canProduce = false;
            }

            $details[] = [
                'material' => $bom->material->name,
                'required' => $required,
                'available' => $available,
                'sufficient' => $sufficient,
                'shortage' => $sufficient ? 0 : ($required - $available),
            ];
        }

        return [
            'can_produce' => $canProduce,
            'materials' => $details,
        ];
    }

    /**
     * Get production statistics
     */
    public function getProductionStatistics(array $filters = []): array
    {
        $query = ProductionOrder::query();

        if (!empty($filters['from_date'])) {
            $query->whereDate('start_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('end_date', '<=', $filters['to_date']);
        }

        return [
            'total_orders' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'total_quantity_produced' => (clone $query)->where('status', 'completed')->sum('quantity'),
        ];
    }
}
