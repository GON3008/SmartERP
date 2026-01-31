<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\ActivityLogService;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    protected $orderService;
    protected $logService;

    public function __construct(OrderService $orderService, ActivityLogService $logService)
    {
        $this->orderService = $orderService;
        $this->logService = $logService;
    }

    /**
     * Display a listing of orders.
     */

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'customer_id', 'form_date', 'to_date', 'sort_by', 'sort_order', 'per_page']);
        $orders = $this->orderService->getAllOrders($filters);

        return response()->json($orders);
    }

    /**
     * Store a newly created order.
     */

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder($request->validated());

            $this->logService->log('created', 'orders', $order->id, "Tạo đơn hàng: {$order->id}");

            return response()->json([
                'message' => 'Create order successfully!',
                'data' => $order,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified order.
     */

    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id);

            return response()->json([
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Order not found!' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified order.
     */

    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->updateOrder($id, $request->validated());

            $this->logService->log('updated', 'orders', $order->id, "Cập nhật đơn hàng: {$order->order_code}");

            return response()->json([
                'message' => 'Update order successfully!',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified order
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->orderService->deleteOrder($id);

            $this->logService->log('deleted', 'orders', $id, "Xóa đơn hàng ID: {$id}");

            return response()->json([
                'message' => 'Xóa đơn hàng thành công!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Process order (update inventory)
     */
    public function process(Request $request, int $id): JsonResponse
    {
        $request->validate(['warehouse_id' => 'required|exists:warehouses,id']);

        try {
            $order = $this->orderService->processOrder($id, $request->warehouse_id);

            $this->logService->log('processed', 'orders', $order->id, "Xử lý đơn hàng: {$order->order_code}");

            return response()->json([
                'message' => 'Xử lý đơn hàng thành công!',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Cancel order
     */
    public function cancel(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder($id);

            $this->logService->log('cancelled', 'orders', $order->id, "Hủy đơn hàng: {$order->order_code}");

            return response()->json([
                'message' => 'Hủy đơn hàng thành công!',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get order statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date']);
        $stats = $this->orderService->getOrderStatistics($filters);

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Get top selling products
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $products = $this->orderService->getTopSellingProducts($limit);

        return response()->json([
            'data' => $products
        ]);
    }
}
