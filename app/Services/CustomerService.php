<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Get all customers
     */
    public function getAllCustomers(array $filters = [])
    {
        $query = Customer::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->withCount('orders')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(int $id)
    {
        return Customer::with([
            'orders' => function ($q) {
                $q->orderBy('order_date', 'desc');
            }
        ])->findOrFail($id);
    }

    /**
     * Create customer
     */
    public function createCustomer(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * Update customer
     */
    public function updateCustomer(int $id, array $data): Customer
    {
        $customer = Customer::findOrFail($id);
        $customer->update($data);

        return $customer->fresh();
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(int $id): bool
    {
        $customer = Customer::findOrFail($id);

        // Check if customer has orders
        if ($customer->orders()->exists()) {
            throw new \Exception('Không thể xóa khách hàng đã có đơn hàng!');
        }

        return $customer->delete();
    }

    /**
     * Get customer orders
     */
    public function getCustomerOrders(int $customerId, array $filters = [])
    {
        $customer = Customer::findOrFail($customerId);

        $query = $customer->orders();

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        return $query->with('items.product')
            ->orderBy('order_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(int $customerId): array
    {
        $customer = Customer::with('orders')->findOrFail($customerId);

        $totalOrders = $customer->orders->count();
        $completedOrders = $customer->orders->where('status', 'completed')->count();
        $totalRevenue = $customer->orders->where('status', 'completed')->sum('total_amount');
        $averageOrderValue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0;

        return [
            'customer' => $customer,
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'pending_orders' => $customer->orders->where('status', 'pending')->count(),
            'cancelled_orders' => $customer->orders->where('status', 'cancelled')->count(),
            'total_revenue' => $totalRevenue,
            'average_order_value' => round($averageOrderValue, 2),
            'first_order_date' => $customer->orders->min('order_date'),
            'last_order_date' => $customer->orders->max('order_date'),
        ];
    }

    /**
     * Get top customers by revenue
     */
    public function getTopCustomers(int $limit = 10, array $filters = [])
    {
        $query = Customer::withSum([
            'orders' => function ($q) use ($filters) {
                $q->where('status', 'completed');

                if (!empty($filters['from_date'])) {
                    $q->whereDate('order_date', '>=', $filters['from_date']);
                }
                if (!empty($filters['to_date'])) {
                    $q->whereDate('order_date', '<=', $filters['to_date']);
                }
            }
        ], 'total_amount')
            ->orderByDesc('orders_sum_total_amount')
            ->limit($limit);

        return $query->get();
    }

    /**
     * Get customers with no orders
     */
    public function getInactiveCustomers()
    {
        return Customer::doesntHave('orders')
            ->orWhereDoesntHave('orders', function ($q) {
                $q->where('order_date', '>=', now()->subMonths(6));
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Search customers
     */
    public function searchCustomers(string $keyword)
    {
        return Customer::where('name', 'like', "%{$keyword}%")
            ->orWhere('email', 'like', "%{$keyword}%")
            ->orWhere('phone', 'like', "%{$keyword}%")
            ->limit(20)
            ->get();
    }

    /**
     * Get customer summary statistics
     */
    public function getOverallStatistics(): array
    {
        return [
            'total_customers' => Customer::count(),
            'customers_with_orders' => Customer::has('orders')->count(),
            'customers_without_orders' => Customer::doesntHave('orders')->count(),
            'total_revenue' => DB::table('orders')
                ->where('status', 'completed')
                ->sum('total_amount'),
            'average_customer_value' => DB::table('customers')
                ->join('orders', 'customers.id', '=', 'orders.customer_id')
                ->where('orders.status', 'completed')
                ->selectRaw('AVG(orders.total_amount) as avg_value')
                ->value('avg_value'),
        ];
    }

    /**
     * Get customer purchase history report
     */
    public function getCustomerPurchaseHistory(int $customerId)
    {
        $customer = Customer::with([
            'orders.items.product'
        ])->findOrFail($customerId);

        $orders = $customer->orders->map(function ($order) {
            return [
                'order_code' => $order->order_code,
                'order_date' => $order->order_date,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'items_count' => $order->items->count(),
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->quantity * $item->price,
                    ];
                }),
            ];
        });

        return [
            'customer' => $customer,
            'orders' => $orders,
            'summary' => $this->getCustomerStatistics($customerId),
        ];
    }
}
