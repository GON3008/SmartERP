<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductionService;
use App\Services\ActivityLogService;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Http\Requests\Production\UpdateProductionOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductionController extends Controller
{
    protected $productionService;
    protected $logService;

    public function __construct(ProductionService $productionService, ActivityLogService $logService)
    {
        $this->productionService = $productionService;
        $this->logService = $logService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'product_id', 'from_date', 'to_date', 'per_page']);
        $orders = $this->productionService->getAllProductionOrders($filters);

        return response()->json($orders);
    }

    public function store(StoreProductionOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->productionService->createProductionOrder($request->validated());
            $this->logService->log('created', 'production_orders', $order->id, "Tạo lệnh SX: {$order->order_code}");

            return response()->json([
                'message' => 'Tạo lệnh sản xuất thành công!',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function update(UpdateProductionOrderRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->productionService->updateProductionOrder($id, $request->validated());
            return response()->json(['message' => 'Cập nhật thành công!', 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $request->validate(['warehouse_id' => 'required|exists:warehouses,id']);

        try {
            $order = $this->productionService->startProduction($id, $request->warehouse_id);
            $this->logService->log('started', 'production_orders', $order->id, "Bắt đầu SX: {$order->order_code}");

            return response()->json(['message' => 'Bắt đầu sản xuất thành công!', 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'actual_quantity' => 'nullable|integer|min:1',
        ]);

        try {
            $order = $this->productionService->completeProduction($id, $request->warehouse_id, $request->actual_quantity);
            $this->logService->log('completed', 'production_orders', $order->id, "Hoàn thành SX: {$order->order_code}");

            return response()->json(['message' => 'Hoàn thành sản xuất thành công!', 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->productionService->cancelProduction($id, $request->reason);
            return response()->json(['message' => 'Hủy lệnh sản xuất thành công!', 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function checkMaterials(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $result = $this->productionService->canProduce(
            $request->product_id,
            $request->quantity,
            $request->warehouse_id
        );

        return response()->json(['data' => $result]);
    }
}
