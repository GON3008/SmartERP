<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function getAllSuppliers(array $filters = [])
    {
        $query = Supplier::query();

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('contact_person', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('tax_code', 'like', "%{$s}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy    = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    public function getSupplierById(int $id): Supplier
    {
        return Supplier::findOrFail($id);
    }

    public function createSupplier(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function updateSupplier(int $id, array $data): Supplier
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function deleteSupplier(int $id): void
    {
        $supplier = Supplier::findOrFail($id);

        if ($supplier->purchaseOrders()->exists()) {
            throw new \Exception('Không thể xóa nhà cung cấp đã có phiếu mua hàng.');
        }

        $supplier->delete();
    }

    public function getSupplierStatistics(int $id): array
    {
        $supplier = Supplier::findOrFail($id);

        $totalPO    = $supplier->purchaseOrders()->count();
        $totalSpent = $supplier->purchaseOrders()
            ->where('status', 'received')
            ->sum('total_amount');
        $pendingPO  = $supplier->purchaseOrders()
            ->whereIn('status', ['draft', 'confirmed'])
            ->count();

        return [
            'total_purchase_orders' => $totalPO,
            'total_spent'           => $totalSpent,
            'pending_orders'        => $pendingPO,
        ];
    }

    public function getPurchaseHistory(int $id, array $filters = [])
    {
        $query = Supplier::findOrFail($id)
            ->purchaseOrders()
            ->with('items.product')
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}
