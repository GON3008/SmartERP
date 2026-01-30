<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\StockIn;
use App\Models\StockOut;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    /**
     * Get all warehouses
     */
    public function getAllWarehouses(array $filters = [])
    {
        $query = Warehouse::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return $query->withCount('inventories')
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get warehouse by ID
     */
    public function getWarehouseById(int $id)
    {
        return Warehouse::with([
            'inventories.product',
            'stockIns' => function ($q) {
                $q->orderBy('import_date', 'desc')->limit(10);
            },
            'stockOuts' => function ($q) {
                $q->orderBy('export_date', 'desc')->limit(10);
            }
        ])->findOrFail($id);
    }

    /**
     * Create warehouse
     */
    public function createWarehouse(array $data): Warehouse
    {
        return DB::transaction(function () use ($data) {
            $warehouse = Warehouse::create($data);

            // Auto create inventory records for all existing products
            $products = \App\Models\Product::all();
            foreach ($products as $product) {
                Inventory::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 0,
                ]);
            }

            return $warehouse->load('inventories');
        });
    }

    /**
     * Update warehouse
     */
    public function updateWarehouse(int $id, array $data): Warehouse
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update($data);

        return $warehouse->fresh();
    }

    /**
     * Delete warehouse
     */
    public function deleteWarehouse(int $id): bool
    {
        $warehouse = Warehouse::findOrFail($id);

        // Check if warehouse has inventory
        if ($warehouse->inventories()->where('quantity', '>', 0)->exists()) {
            throw new \Exception('Không thể xóa kho còn hàng tồn kho!');
        }

        // Check if warehouse has pending stock movements
        if ($warehouse->stockIns()->exists() || $warehouse->stockOuts()->exists()) {
            throw new \Exception('Không thể xóa kho đã có lịch sử nhập/xuất!');
        }

        return DB::transaction(function () use ($warehouse) {
            $warehouse->inventories()->delete();
            return $warehouse->delete();
        });
    }

    /**
     * Get warehouse inventory report
     */
    public function getWarehouseInventoryReport(int $warehouseId, array $filters = [])
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        $query = $warehouse->inventories()->with('product');

        // Filter by category
        if (!empty($filters['category'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('category', $filters['category']);
            });
        }

        // Filter low stock
        if (!empty($filters['low_stock'])) {
            $query->whereRaw('quantity <= (SELECT min_stock FROM products WHERE products.id = inventories.product_id)');
        }

        // Filter out of stock
        if (!empty($filters['out_of_stock'])) {
            $query->where('quantity', 0);
        }

        $inventories = $query->get();

        return [
            'warehouse' => $warehouse,
            'total_products' => $inventories->count(),
            'total_quantity' => $inventories->sum('quantity'),
            'low_stock_items' => $inventories->filter(function ($inv) {
                return $inv->quantity <= $inv->product->min_stock;
            })->count(),
            'out_of_stock_items' => $inventories->where('quantity', 0)->count(),
            'total_value' => $inventories->sum(function ($inv) {
                return $inv->quantity * $inv->product->price;
            }),
            'inventories' => $inventories->map(function ($inv) {
                return [
                    'product_id' => $inv->product_id,
                    'sku' => $inv->product->sku,
                    'product_name' => $inv->product->name,
                    'category' => $inv->product->category,
                    'quantity' => $inv->quantity,
                    'min_stock' => $inv->product->min_stock,
                    'unit' => $inv->product->unit,
                    'price' => $inv->product->price,
                    'value' => $inv->quantity * $inv->product->price,
                    'status' => $inv->quantity == 0 ? 'Out of Stock' : ($inv->quantity <= $inv->product->min_stock ? 'Low Stock' : 'OK'),
                ];
            }),
        ];
    }

    /**
     * Get warehouse stock movements
     */
    public function getWarehouseStockMovements(int $warehouseId, array $filters = [])
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        $stockIns = StockIn::where('warehouse_id', $warehouseId)
            ->when(!empty($filters['product_id']), function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            })
            ->when(!empty($filters['from_date']), function ($q) use ($filters) {
                $q->whereDate('import_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($q) use ($filters) {
                $q->whereDate('import_date', '<=', $filters['to_date']);
            })
            ->with('product')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'In',
                    'date' => $item->import_date,
                    'product' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'note' => $item->note,
                ];
            });

        $stockOuts = StockOut::where('warehouse_id', $warehouseId)
            ->when(!empty($filters['product_id']), function ($q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            })
            ->when(!empty($filters['from_date']), function ($q) use ($filters) {
                $q->whereDate('export_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($q) use ($filters) {
                $q->whereDate('export_date', '<=', $filters['to_date']);
            })
            ->with('product')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Out',
                    'date' => $item->export_date,
                    'product' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'note' => $item->reason,
                ];
            });

        $movements = $stockIns->concat($stockOuts)->sortByDesc('date')->values();

        return [
            'warehouse' => $warehouse,
            'total_movements' => $movements->count(),
            'total_in' => $stockIns->sum('quantity'),
            'total_out' => $stockOuts->sum('quantity'),
            'movements' => $movements,
        ];
    }

    /**
     * Get warehouse capacity utilization
     */
    public function getWarehouseCapacity(int $warehouseId): array
    {
        $warehouse = Warehouse::with('inventories.product')->findOrFail($warehouseId);

        $totalItems = $warehouse->inventories->sum('quantity');
        $totalProducts = $warehouse->inventories->count();
        $totalValue = $warehouse->inventories->sum(function ($inv) {
            return $inv->quantity * $inv->product->price;
        });

        return [
            'warehouse' => $warehouse,
            'total_items' => $totalItems,
            'total_products' => $totalProducts,
            'total_value' => $totalValue,
            'by_category' => $warehouse->inventories->groupBy('product.category')->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'products_count' => $items->count(),
                    'total_quantity' => $items->sum('quantity'),
                    'total_value' => $items->sum(function ($inv) {
                        return $inv->quantity * $inv->product->price;
                    }),
                ];
            })->values(),
        ];
    }

    /**
     * Compare warehouses
     */
    public function compareWarehouses(array $warehouseIds): array
    {
        $warehouses = Warehouse::whereIn('id', $warehouseIds)
            ->with('inventories.product')
            ->get();

        return $warehouses->map(function ($warehouse) {
            return [
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'location' => $warehouse->location,
                'total_products' => $warehouse->inventories->count(),
                'total_quantity' => $warehouse->inventories->sum('quantity'),
                'total_value' => $warehouse->inventories->sum(function ($inv) {
                    return $inv->quantity * $inv->product->price;
                }),
                'low_stock_items' => $warehouse->inventories->filter(function ($inv) {
                    return $inv->quantity <= $inv->product->min_stock;
                })->count(),
            ];
        })->toArray();
    }

    /**
     * Get warehouse statistics
     */
    public function getWarehouseStatistics(): array
    {
        return [
            'total_warehouses' => Warehouse::count(),
            'total_inventory_value' => Inventory::join('products', 'inventories.product_id', '=', 'products.id')
                ->selectRaw('SUM(inventories.quantity * products.price) as total')
                ->value('total'),
            'total_items' => Inventory::sum('quantity'),
            'warehouses' => Warehouse::withCount('inventories')
                ->get()
                ->map(function ($warehouse) {
                    return [
                        'name' => $warehouse->name,
                        'location' => $warehouse->location,
                        'products_count' => $warehouse->inventories_count,
                    ];
                }),
        ];
    }
}
