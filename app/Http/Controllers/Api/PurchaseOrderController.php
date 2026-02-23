<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PurchaseOrderService;
use App\Http\Resources\PurchaseOrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $poService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'supplier_id', 'from_date', 'to_date', 'sort_by', 'sort_order', 'per_page']);
        $orders  = $this->poService->getAllPurchaseOrders($filters);

        return response()->json([
            'success' => true,
            'data'    => PurchaseOrderResource::collection($orders),
            'meta'    => [
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id'        => 'required|exists:suppliers,id',
            'order_date'         => 'nullable|date',
            'expected_date'      => 'nullable|date',
            'notes'              => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $po = $this->poService->createPurchaseOrder($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Tạo phiếu mua hàng thành công!',
                'data'    => new PurchaseOrderResource($po),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $po = $this->poService->getPurchaseOrderById($id);
        return response()->json(['success' => true, 'data' => new PurchaseOrderResource($po)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'supplier_id'        => 'sometimes|exists:suppliers,id',
            'order_date'         => 'nullable|date',
            'expected_date'      => 'nullable|date',
            'notes'              => 'nullable|string|max:1000',
            'items'              => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity'   => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        try {
            $po = $this->poService->updatePurchaseOrder($id, $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phiếu mua hàng thành công!',
                'data'    => new PurchaseOrderResource($po),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->poService->deletePurchaseOrder($id);
            return response()->json(['success' => true, 'message' => 'Xóa phiếu mua hàng thành công!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function confirm(int $id): JsonResponse
    {
        try {
            $po = $this->poService->confirmPurchaseOrder($id);
            return response()->json([
                'success' => true,
                'message' => 'Xác nhận phiếu mua hàng thành công!',
                'data'    => new PurchaseOrderResource($po),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $request->validate(['warehouse_id' => 'required|exists:warehouses,id']);

        try {
            $po = $this->poService->receivePurchaseOrder($id, $request->warehouse_id);
            return response()->json([
                'success' => true,
                'message' => 'Nhận hàng thành công! Đã tự động nhập kho.',
                'data'    => new PurchaseOrderResource($po),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        try {
            $po = $this->poService->cancelPurchaseOrder($id);
            return response()->json([
                'success' => true,
                'message' => 'Hủy phiếu mua hàng thành công!',
                'data'    => new PurchaseOrderResource($po),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['from_date', 'to_date']);
        return response()->json([
            'success' => true,
            'data'    => $this->poService->getStatistics($filters),
        ]);
    }
}
