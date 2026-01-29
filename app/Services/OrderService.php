<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Inventory;
use App\Models\StockOut;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Get all orders with filters
     */
    public function getAllOrders(array $filters = [])
    {
        $query = Order::query();

        // Search by order code or customer name
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by customer
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->with(['customer', 'items.product'])->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get order by ID
     */
    public function getOrderById(int $id)
    {
        return Order::with(['customer', 'items.product'])->findOrFail($id);
    }

    /**
     * Create new order
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Create order
            $order = Order::create([
                'customer_id' => $data['customer_id'],
                'order_code' => $data['order_code'],
                'order_date' => $data['order_date'],
                'status' => $data['status'] ?? 'pending',
                'total_amount' => 0, // Will calculate later
            ]);

            // Create order items and calculate total
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                $totalAmount += ($item['quantity'] * $item['price']);
            }

            // Update total amount
            $order->update(['total_amount' => $totalAmount]);

            return $order->load(['customer', 'items.product']);
        });
    }

    /**
     * Update order
     */
    public function updateOrder(int $id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = Order::findOrFail($id);

            // Check if order can be updated
            if ($order->status === 'completed') {
                throw new \Exception('Không thể cập nhật đơn hàng đã hoàn thành!');
            }

            // Update order basic info
            $order->update([
                'customer_id' => $data['customer_id'] ?? $order->customer_id,
                'order_date' => $data['order_date'] ?? $order->order_date,
                'status' => $data['status'] ?? $order->status,
            ]);

            // Update items if provided
            if (!empty($data['items'])) {
                // Delete old items
                $order->items()->delete();

                // Create new items and calculate total
                $totalAmount = 0;
                foreach ($data['items'] as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);

                    $totalAmount += ($item['quantity'] * $item['price']);
                }

                $order->update(['total_amount' => $totalAmount]);
            }

            return $order->load(['customer', 'items.product']);
        });
    }

    /**
     * Process order (update inventory)
     */
    public function processOrder(int $id, int $warehouseId): Order
    {
        return DB::transaction(function () use ($id, $warehouseId) {
            $order = Order::with('items.product')->findOrFail($id);

            // Check order status
            if ($order->status === 'completed') {
                throw new \Exception('Đơn hàng đã được xử lý!');
            }

            if ($order->status === 'cancelled') {
                throw new \Exception('Không thể xử lý đơn hàng đã hủy!');
            }

            // Check inventory for all items
            foreach ($order->items as $item) {
                $inventory = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if (!$inventory || $inventory->quantity < $item->quantity) {
                    throw new \Exception(
                        "Không đủ hàng trong kho cho sản phẩm: {$item->product->name}. " .
                            "Cần: {$item->quantity}, Có: " . ($inventory->quantity ?? 0)
                    );
                }
            }

            // Process each item
            foreach ($order->items as $item) {
                // Update inventory
                $inventory = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $inventory->decrement('quantity', $item->quantity);

                // Create stock out record
                StockOut::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $item->quantity,
                    'export_date' => now(),
                    'reason' => 'Sale - Order: ' . $order->order_code,
                ]);
            }

            // Update order status
            $order->update(['status' => 'completed']);

            return $order->fresh(['customer', 'items.product']);
        });
    }

    /**
     * Cancel order
     */
    public function cancelOrder(int $id): Order
    {
        $order = Order::findOrFail($id);

        if ($order->status === 'completed') {
            throw new \Exception('Không thể hủy đơn hàng đã hoàn thành!');
        }

        $order->update(['status' => 'cancelled']);

        return $order->load(['customer', 'items.product']);
    }

    /**
     * Delete order
     */
    public function deleteOrder(int $id): bool
    {
        $order = Order::findOrFail($id);

        if ($order->status === 'completed') {
            throw new \Exception('Không thể xóa đơn hàng đã hoàn thành!');
        }

        return DB::transaction(function () use ($order) {
            $order->items()->delete();
            return $order->delete();
        });
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics(array $filters = []): array
    {
        $query = Order::query();

        // Apply date filters
        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        return [
            'total_orders' => (clone $query)->count(),
            'pending_orders' => (clone $query)->where('status', 'pending')->count(),
            'processing_orders' => (clone $query)->where('status', 'processing')->count(),
            'completed_orders' => (clone $query)->where('status', 'completed')->count(),
            'cancelled_orders' => (clone $query)->where('status', 'cancelled')->count(),
            'total_revenue' => (clone $query)->where('status', 'completed')->sum('total_amount'),
            'average_order_value' => (clone $query)->where('status', 'completed')->avg('total_amount'),
        ];
    }

    /**
     * Get top selling products
     */
    public function getTopSellingProducts(int $limit = 10)
    {
        return OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->whereHas('order', function ($q) {
                $q->where('status', 'completed');
            })
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->with('product')
            ->get();
    }
}
