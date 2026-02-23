<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockIn;
use App\Models\Inventory;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    public function getAllPurchaseOrders(array $filters = [])
    {
        $query = PurchaseOrder::with(['supplier', 'items.product']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('po_code', 'like', "%{$s}%")
                  ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$s}%"));
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        $sortBy    = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getPurchaseOrderById(int $id): PurchaseOrder
    {
        return PurchaseOrder::with(['supplier', 'items.product', 'stockIns'])->findOrFail($id);
    }

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $poCode = 'PO-' . date('Ymd') . '-' . str_pad(
                PurchaseOrder::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT
            );

            $po = PurchaseOrder::create([
                'po_code'       => $poCode,
                'supplier_id'   => $data['supplier_id'],
                'order_date'    => $data['order_date'] ?? now()->toDateString(),
                'expected_date' => $data['expected_date'] ?? null,
                'status'        => 'draft',
                'notes'         => $data['notes'] ?? null,
                'total_amount'  => 0,
            ]);

            $total = 0;
            foreach ($data['items'] as $item) {
                $totalPrice = $item['quantity'] * $item['unit_price'];
                $po->items()->create([
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total_price' => $totalPrice,
                ]);
                $total += $totalPrice;
            }

            $po->update(['total_amount' => $total]);
            return $po->load(['supplier', 'items.product']);
        });
    }

    public function updatePurchaseOrder(int $id, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $data) {
            $po = PurchaseOrder::findOrFail($id);

            if ($po->status !== 'draft') {
                throw new \Exception('Chỉ có thể chỉnh sửa phiếu mua hàng ở trạng thái Nháp.');
            }

            $po->update([
                'supplier_id'   => $data['supplier_id'] ?? $po->supplier_id,
                'order_date'    => $data['order_date'] ?? $po->order_date,
                'expected_date' => $data['expected_date'] ?? $po->expected_date,
                'notes'         => $data['notes'] ?? $po->notes,
            ]);

            if (isset($data['items'])) {
                $po->items()->delete();
                $total = 0;
                foreach ($data['items'] as $item) {
                    $totalPrice = $item['quantity'] * $item['unit_price'];
                    $po->items()->create([
                        'product_id'  => $item['product_id'],
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['unit_price'],
                        'total_price' => $totalPrice,
                    ]);
                    $total += $totalPrice;
                }
                $po->update(['total_amount' => $total]);
            }

            return $po->fresh(['supplier', 'items.product']);
        });
    }

    public function confirmPurchaseOrder(int $id): PurchaseOrder
    {
        return DB::transaction(function () use ($id) {
            $po = PurchaseOrder::findOrFail($id);

            if ($po->status !== 'draft') {
                throw new \Exception('Chỉ có thể xác nhận phiếu mua hàng ở trạng thái Nháp.');
            }

            $po->update(['status' => 'confirmed']);

            // Create Account Payable (mirrors what InvoiceService does for receivables)
            $existing = Account::where('reference_type', 'App\\Models\\PurchaseOrder')
                ->where('reference_id', $id)->exists();

            if (!$existing) {
                Account::create([
                    'type'           => 'payable',
                    'contact_type'   => 'App\\Models\\Supplier',
                    'contact_id'     => $po->supplier_id,
                    'reference_type' => 'App\\Models\\PurchaseOrder',
                    'reference_id'   => $po->id,
                    'total_amount'   => $po->total_amount,
                    'paid_amount'    => 0,
                    'balance'        => $po->total_amount,
                    'due_date'       => $po->expected_date,
                    'status'         => 'open',
                ]);
            }

            return $po->fresh(['supplier', 'items.product']);
        });
    }

    public function receivePurchaseOrder(int $id, int $warehouseId): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $warehouseId) {
            $po = PurchaseOrder::with('items.product')->findOrFail($id);

            if ($po->status !== 'confirmed') {
                throw new \Exception('Chỉ có thể nhận hàng từ phiếu mua hàng đã xác nhận.');
            }

            foreach ($po->items as $item) {
                // Create stock in record
                StockIn::create([
                    'product_id'        => $item->product_id,
                    'warehouse_id'      => $warehouseId,
                    'quantity'          => $item->quantity,
                    'import_date'       => now()->toDateString(),
                    'note'              => "Nhập từ PO: {$po->po_code}",
                    'purchase_order_id' => $po->id,
                ]);

                // Update inventory
                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $item->product_id, 'warehouse_id' => $warehouseId],
                    ['quantity' => 0]
                );
                $inventory->increment('quantity', $item->quantity);

                // Update received quantity
                $item->update(['received_quantity' => $item->quantity]);
            }

            $po->update(['status' => 'received']);
            return $po->fresh(['supplier', 'items.product', 'stockIns']);
        });
    }

    public function cancelPurchaseOrder(int $id): PurchaseOrder
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status === 'received') {
            throw new \Exception('Không thể hủy phiếu mua hàng đã nhận.');
        }

        $po->update(['status' => 'cancelled']);
        return $po->fresh(['supplier', 'items.product']);
    }

    public function deletePurchaseOrder(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'draft') {
            throw new \Exception('Chỉ có thể xóa phiếu mua hàng ở trạng thái Nháp.');
        }

        $po->delete();
    }

    public function getStatistics(array $filters = []): array
    {
        $query = PurchaseOrder::query();

        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        return [
            'total'     => $query->count(),
            'draft'     => (clone $query)->where('status', 'draft')->count(),
            'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
            'received'  => (clone $query)->where('status', 'received')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'total_amount' => (clone $query)->where('status', 'received')->sum('total_amount'),
        ];
    }
}
