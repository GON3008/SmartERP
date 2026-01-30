<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Order;
use App\Models\ProductionOrder;

class NotificationService
{
    /**
     * Get low stock notifications
     */
    public function getLowStockNotifications(): array
    {
        $lowStockProducts = Product::whereHas('inventories', function ($q) {
            $q->whereRaw('quantity <= products.min_stock');
        })
            ->with('inventories.warehouse')
            ->get();

        return $lowStockProducts->map(function ($product) {
            return [
                'type' => 'low_stock',
                'severity' => 'warning',
                'title' => 'Cảnh báo tồn kho thấp',
                'message' => "Sản phẩm {$product->name} sắp hết hàng",
                'product_id' => $product->id,
                'product_name' => $product->name,
                'warehouses' => $product->inventories->map(fn($inv) => [
                    'warehouse' => $inv->warehouse->name,
                    'quantity' => $inv->quantity,
                    'min_stock' => $product->min_stock,
                ]),
            ];
        })->toArray();
    }

    /**
     * Get pending order notifications
     */
    public function getPendingOrderNotifications(): array
    {
        $pendingOrders = Order::where('status', 'pending')
            ->where('order_date', '<', now()->subDays(2))
            ->with('customer')
            ->get();

        return $pendingOrders->map(function ($order) {
            return [
                'type' => 'pending_order',
                'severity' => 'info',
                'title' => 'Đơn hàng chờ xử lý',
                'message' => "Đơn hàng {$order->order_code} đang chờ xử lý",
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'customer' => $order->customer->name,
                'days_pending' => now()->diffInDays($order->order_date),
            ];
        })->toArray();
    }

    /**
     * Get production notifications
     */
    public function getProductionNotifications(): array
    {
        $productionOrders = ProductionOrder::where('status', 'in_progress')
            ->where('start_date', '<', now()->subDays(7))
            ->with('product')
            ->get();

        return $productionOrders->map(function ($order) {
            return [
                'type' => 'production_delay',
                'severity' => 'warning',
                'title' => 'Sản xuất chậm tiến độ',
                'message' => "Lệnh sản xuất {$order->order_code} đang bị chậm",
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'product' => $order->product->name,
                'days_in_progress' => now()->diffInDays($order->start_date),
            ];
        })->toArray();
    }

    /**
     * Get all notifications
     */
    public function getAllNotifications(): array
    {
        return array_merge(
            $this->getLowStockNotifications(),
            $this->getPendingOrderNotifications(),
            $this->getProductionNotifications()
        );
    }

    /**
     * Get notification count
     */
    public function getNotificationCount(): int
    {
        return count($this->getAllNotifications());
    }
}
