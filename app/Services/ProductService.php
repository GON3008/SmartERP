<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use PhpParser\Node\Expr\FuncCall;

class ProductService
{
    /**
     * Get all products with filters
     */

    public function getAllProducts(array $filters = [])
    {
        $query = Product::query();

        //search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->when(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        //Filter by category
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        //filter low stock
        if (!empty($filters['low_stock'])) {
            $query->whereHas('inventories', function ($q) {
                $q->whereRaw('quantity <= products.min_stock');
            });
        }

        //Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // With relationship
        $query->with('inventories.wareHouse');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get product by id
     */

    public function getProductById(int $id)
    {
        return Product::with([
            'inventories.wareHouse',
            'billOfMaterials.material',
            'productionOrders',
            'inventoryRecommendations',
        ])->findOrFail($id);
    }

    /**
     * Create new product
     */

    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // Create product
            $product = Product::create($data);

            // Auto create inventory records for all warehouses
            $warehouses = \App\Models\Warehouse::all();
            foreach ($warehouses as $warehouse) {
                Inventory::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 0,
                ]);
            }

            return $product->load('inventories.warehouse');
        });
    }

    /**
     * Update product
     */
    public function updateProduct(int $id, array $data): Product
    {
        $product = Product::findOrFail($id);
        $product->update($data);

        return $product->load('inventories.warehouse');
    }

    /**
     * Delete product
     */
    public function deleteProduct(int $id): bool
    {
        $product = Product::findOrFail($id);

        // Check if product is used in orders
        if ($product->orderItems()->exists()) {
            throw new \Exception('Không thể xóa sản phẩm đã có trong đơn hàng!');
        }

        // Check if product has inventory
        if ($product->inventories()->where('quantity', '>', 0)->exists()) {
            throw new \Exception('Không thể xóa sản phẩm còn tồn kho!');
        }

        // Check if product has pending production orders
        if ($product->productionOrders()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            throw new \Exception('Không thể xóa sản phẩm có lệnh sản xuất đang xử lý!');
        }

        return DB::transaction(function () use ($product) {
            // Delete related records
            $product->inventories()->delete();
            $product->billOfMaterials()->delete();
            $product->materialsUsedIn()->delete();

            return $product->delete();
        });
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(): Collection
    {
        return Product::whereHas('inventories', function ($query) {
            $query->whereRaw('quantity <= products.min_stock');
        })
            ->with(['inventories.warehouse'])
            ->get();
    }

    /**
     * Get total stock for a product
     */
    public function getTotalStock(int $productId): int
    {
        return Inventory::where('product_id', $productId)->sum('quantity');
    }

    /**
     * Get stock by warehouse
     */
    public function getStockByWarehouse(int $productId, int $warehouseId): int
    {
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $inventory ? $inventory->quantity : 0;
    }

    /**
     * Check if product is available in warehouse
     */
    public function isAvailable(int $productId, int $warehouseId, int $quantity): bool
    {
        $stock = $this->getStockByWarehouse($productId, $warehouseId);
        return $stock >= $quantity;
    }

    /**
     * Get products by category
     */
    public function getProductsByCategory(string $category)
    {
        return Product::where('category', $category)
            ->with(['inventories'])
            ->paginate(15);
    }

    /**
     * Search products
     */
    public function searchProducts(string $keyword)
    {
        return Product::where('sku', 'like', "%{$keyword}%")
            ->orWhere('name', 'like', "%{$keyword}%")
            ->with(['inventories'])
            ->limit(20)
            ->get();
    }
}
