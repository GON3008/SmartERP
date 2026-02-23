<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SupplierService;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function __construct(protected SupplierService $supplierService) {}

    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['search', 'status', 'sort_by', 'sort_order', 'per_page']);
        $suppliers = $this->supplierService->getAllSuppliers($filters);

        return response()->json([
            'success' => true,
            'data'    => SupplierResource::collection($suppliers),
            'meta'    => [
                'total'        => $suppliers->total(),
                'per_page'     => $suppliers->perPage(),
                'current_page' => $suppliers->currentPage(),
                'last_page'    => $suppliers->lastPage(),
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierService->createSupplier($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tạo nhà cung cấp thành công!',
            'data'    => new SupplierResource($supplier),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $supplier = $this->supplierService->getSupplierById($id);

        return response()->json([
            'success' => true,
            'data'    => new SupplierResource($supplier),
        ]);
    }

    public function update(UpdateSupplierRequest $request, int $id): JsonResponse
    {
        $supplier = $this->supplierService->updateSupplier($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật nhà cung cấp thành công!',
            'data'    => new SupplierResource($supplier),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->supplierService->deleteSupplier($id);
            return response()->json(['success' => true, 'message' => 'Xóa nhà cung cấp thành công!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function purchaseHistory(Request $request, int $id): JsonResponse
    {
        $filters = $request->only(['status', 'per_page']);
        $history = $this->supplierService->getPurchaseHistory($id, $filters);

        return response()->json(['success' => true, 'data' => $history]);
    }

    public function statistics(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->supplierService->getSupplierStatistics($id),
        ]);
    }
}
