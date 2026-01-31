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
}
