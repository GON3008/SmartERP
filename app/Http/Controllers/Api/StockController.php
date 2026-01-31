<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use App\Services\ActivityLogService;
use App\Http\Requests\Stock\StoreStockInRequest;
use App\Http\Requests\Stock\StoreStockOutRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    protected $stockService;
    protected $logService;

    public function __construct(StockService $stockService, ActivityLogService $logService)
    {
        $this->stockService = $stockService;
        $this->logService = $logService;
    }

    public function stockIn(StoreStockInRequest $request): JsonResponse
    {
        try {
            $stockIn = $this->stockService->stockIn($request->validated());
            $this->logService->log('stock_in', 'stock_in', $stockIn->id, "Nhập kho: {$stockIn->product->name}");

            return response()->json([
                'message' => 'Nhập kho thành công!',
                'data' => $stockIn
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function stockOut(StoreStockOutRequest $request): JsonResponse
    {
        try {
            $stockOut = $this->stockService->stockOut($request->validated());
            $this->logService->log('stock_out', 'stock_out', $stockOut->id, "Xuất kho: {$stockOut->product->name}");

            return response()->json([
                'message' => 'Xuất kho thành công!',
                'data' => $stockOut
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        try {
            $result = $this->stockService->transferStock(
                $request->product_id,
                $request->from_warehouse_id,
                $request->to_warehouse_id,
                $request->quantity,
                $request->note
            );

            return response()->json([
                'message' => 'Chuyển kho thành công!',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function stockInHistory(Request $request): JsonResponse
    {
        $filters = $request->only(['product_id', 'warehouse_id', 'from_date', 'to_date', 'per_page']);
        $history = $this->stockService->getStockInHistory($filters);

        return response()->json($history);
    }

    public function stockOutHistory(Request $request): JsonResponse
    {
        $filters = $request->only(['product_id', 'warehouse_id', 'reason', 'from_date', 'to_date', 'per_page']);
        $history = $this->stockService->getStockOutHistory($filters);

        return response()->json($history);
    }

    public function inventoryReport(int $warehouseId): JsonResponse
    {
        $report = $this->stockService->getInventoryReport($warehouseId);
        return response()->json(['data' => $report]);
    }
}
