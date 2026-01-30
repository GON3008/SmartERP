<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\ProductionOrder;
use App\Models\Employee;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Get dashboard statistics
     */
    public function getDashboardStatistics(): array
    {
        return [
            'overview' => $this->getOverviewStatistics(),
            'sales' => $this->getSalesStatistics(),
            'inventory' => $this->getInventoryStatistics(),
            'production' => $this->getProductionStatistics(),
            'hr' => $this->getHRStatistics(),
        ];
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStatistics(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'today_orders' => Order::whereDate('order_date', $today)->count(),
            'today_revenue' => Order::whereDate('order_date', $today)
                ->where('status', 'completed')
                ->sum('total_amount'),
            'month_orders' => Order::whereDate('order_date', '>=', $thisMonth)->count(),
            'month_revenue' => Order::whereDate('order_date', '>=', $thisMonth)
                ->where('status', 'completed')
                ->sum('total_amount'),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'low_stock_products' => Product::whereHas('inventories', function ($q) {
                $q->whereRaw('quantity <= products.min_stock');
            })->count(),
        ];
    }

    /**
     * Get sales statistics
     */
    private function getSalesStatistics(array $filters = []): array
    {
        $query = Order::query();

        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        return [
            'total_orders' => (clone $query)->count(),
            'completed_orders' => (clone $query)->where('status', 'completed')->count(),
            'pending_orders' => (clone $query)->where('status', 'pending')->count(),
            'cancelled_orders' => (clone $query)->where('status', 'cancelled')->count(),
            'total_revenue' => (clone $query)->where('status', 'completed')->sum('total_amount'),
            'average_order_value' => (clone $query)->where('status', 'completed')->avg('total_amount'),
        ];
    }

    /**
     * Get inventory statistics
     */
    private function getInventoryStatistics(): array
    {
        return [
            'total_products' => Product::count(),
            'total_inventory_value' => DB::table('inventories')
                ->join('products', 'inventories.product_id', '=', 'products.id')
                ->selectRaw('SUM(inventories.quantity * products.price) as total')
                ->value('total'),
            'total_quantity' => Inventory::sum('quantity'),
            'low_stock_products' => Product::whereHas('inventories', function ($q) {
                $q->whereRaw('quantity <= products.min_stock');
            })->count(),
            'out_of_stock_products' => Product::whereHas('inventories', function ($q) {
                $q->where('quantity', 0);
            })->count(),
        ];
    }

    /**
     * Get production statistics
     */
    private function getProductionStatistics(array $filters = []): array
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
            'total_quantity_produced' => (clone $query)->where('status', 'completed')->sum('quantity'),
        ];
    }

    /**
     * Get HR statistics
     */
    private function getHRStatistics(): array
    {
        return [
            'total_employees' => Employee::count(),
            'active_employees' => Employee::where('status', true)->count(),
            'by_department' => Employee::select('department_id', DB::raw('count(*) as count'))
                ->groupBy('department_id')
                ->with('department:id,name')
                ->get()
                ->map(fn($item) => [
                    'department' => $item->department->name ?? 'Unknown',
                    'count' => $item->count
                ]),
        ];
    }

    /**
     * Get sales report by period
     */
    public function getSalesReport(string $period = 'daily', array $filters = []): array
    {
        $query = Order::where('status', 'completed');

        // Apply date filters
        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        // Group by period
        $groupFormat = match ($period) {
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            'yearly' => '%Y',
            default => '%Y-%m-%d',
        };

        $sales = $query->selectRaw("DATE_FORMAT(order_date, '{$groupFormat}') as period")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total_amount) as revenue')
            ->selectRaw('AVG(total_amount) as avg_order_value')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'period' => $period,
            'data' => $sales,
            'summary' => [
                'total_orders' => $sales->sum('orders_count'),
                'total_revenue' => $sales->sum('revenue'),
                'average_order_value' => $sales->avg('avg_order_value'),
            ],
        ];
    }

    /**
     * Get top selling products report
     */
    public function getTopSellingProductsReport(int $limit = 10, array $filters = [])
    {
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.status', 'completed');

        if (!empty($filters['from_date'])) {
            $query->whereDate('orders.order_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('orders.order_date', '<=', $filters['to_date']);
        }

        return $query->select(
            'products.id',
            'products.sku',
            'products.name',
            'products.category',
            DB::raw('SUM(order_items.quantity) as total_sold'),
            DB::raw('SUM(order_items.quantity * order_items.price) as total_revenue')
        )
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.category')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();
    }

    /**
     * Get inventory movement report
     */
    public function getInventoryMovementReport(array $filters = [])
    {
        $stockIns = DB::table('stock_in')
            ->join('products', 'stock_in.product_id', '=', 'products.id')
            ->when(!empty($filters['from_date']), function ($q) use ($filters) {
                $q->whereDate('import_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($q) use ($filters) {
                $q->whereDate('import_date', '<=', $filters['to_date']);
            })
            ->selectRaw('SUM(stock_in.quantity) as total_in')
            ->value('total_in') ?? 0;

        $stockOuts = DB::table('stock_out')
            ->join('products', 'stock_out.product_id', '=', 'products.id')
            ->when(!empty($filters['from_date']), function ($q) use ($filters) {
                $q->whereDate('export_date', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($q) use ($filters) {
                $q->whereDate('export_date', '<=', $filters['to_date']);
            })
            ->selectRaw('SUM(stock_out.quantity) as total_out')
            ->value('total_out') ?? 0;

        return [
            'total_stock_in' => $stockIns,
            'total_stock_out' => $stockOuts,
            'net_movement' => $stockIns - $stockOuts,
        ];
    }

    /**
     * Get customer report
     */
    public function getCustomerReport(array $filters = [])
    {
        $query = Customer::withCount('orders')
            ->withSum([
                'orders' => function ($q) {
                    $q->where('status', 'completed');
                }
            ], 'total_amount');

        if (!empty($filters['min_orders'])) {
            $query->has('orders', '>=', $filters['min_orders']);
        }

        return $query->orderByDesc('orders_sum_total_amount')
            ->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get production efficiency report
     */
    public function getProductionEfficiencyReport(array $filters = [])
    {
        $query = ProductionOrder::with('product');

        if (!empty($filters['from_date'])) {
            $query->whereDate('start_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('end_date', '<=', $filters['to_date']);
        }

        $orders = $query->get();

        $completedOrders = $orders->where('status', 'completed');

        return [
            'total_orders' => $orders->count(),
            'completed_orders' => $completedOrders->count(),
            'completion_rate' => $orders->count() > 0
                ? round(($completedOrders->count() / $orders->count()) * 100, 2)
                : 0,
            'total_quantity_produced' => $completedOrders->sum('quantity'),
            'average_production_time' => $completedOrders
                ->filter(fn($o) => $o->start_date && $o->end_date)
                ->map(fn($o) => Carbon::parse($o->start_date)->diffInDays(Carbon::parse($o->end_date)))
                ->avg(),
        ];
    }

    /**
     * Get financial summary
     */
    public function getFinancialSummary(array $filters = []): array
    {
        $fromDate = $filters['from_date'] ?? Carbon::now()->startOfMonth();
        $toDate = $filters['to_date'] ?? Carbon::now()->endOfMonth();

        $revenue = Order::whereBetween('order_date', [$fromDate, $toDate])
            ->where('status', 'completed')
            ->sum('total_amount');

        $expenses = DB::table('salaries')
            ->whereBetween(
                DB::raw('CONCAT(year, "-", LPAD(month, 2, "0"), "-01")'),
                [$fromDate, $toDate]
            )
            ->sum('total_salary');

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'profit' => $revenue - $expenses,
            'profit_margin' => $revenue > 0 ? round((($revenue - $expenses) / $revenue) * 100, 2) : 0,
        ];
    }
}
