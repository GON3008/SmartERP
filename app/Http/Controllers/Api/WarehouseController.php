<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WarehouseService;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    protected $warehouseService;

    public function __construct(WarehouseService $warehouseService)
    {
        $this->warehouseService = $warehouseService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'per_page']);
        $warehouse = $this->warehouseService->getAllWarehouses($filters);
        return response()->json([
            'success' => true,
            'data' => WarehouseResource::collection($warehouse),
        ]);
    }

    public function store(StoreWarehouseRequest $request)
    {
        try {
            $warehouse = $this->warehouseService->createWarehouse($request->validated());
            return response()->json([
                'message' => 'Tạo kho thành công!',
                'data' => new WarehouseResource($warehouse),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function show(int $id)
    {
        $warehouseShow = $this->warehouseService->getWarehouseById($id);
        return response()->json([
            'success' => true,
            'data' => new WarehouseResource($warehouseShow)
        ]);
    }

    public function update(UpdateWarehouseRequest $request, int $id)
    {
        $warehouse = $this->warehouseService->updateWarehouse($id, $request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thành công!',
            'data' => new WarehouseResource($warehouse),
            ]);
    }

    public function destroy(int $id)
    {
        try {
            $this->warehouseService->deleteWarehouse($id);
            return response()->json(['message' => 'Xóa kho thành công!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function inventoryReport(Request $request, int $id)
    {
        $filters = $request->only(['category', 'low_stock', 'out_of_stock']);
        return response()->json($this->warehouseService->getWarehouseInventoryReport($id, $filters));
    }

    public function movements(Request $request, int $id)
    {
        $filters = $request->only(['product_id', 'from_date', 'to_date']);
        return response()->json($this->warehouseService->getWarehouseStockMovements($id, $filters));
    }

    public function capacity(int $id)
    {
        return response()->json($this->warehouseService->getWarehouseCapacity($id));
    }
}
