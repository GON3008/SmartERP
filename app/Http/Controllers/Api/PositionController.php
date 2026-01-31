<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PositionService;
use App\Services\ActivityLogService;
use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{
    protected $positionService;
    protected $logService;

    public function __construct(PositionService $positionService, ActivityLogService $logService)
    {
        $this->positionService = $positionService;
        $this->logService = $logService;
    }

    /**
     * Display a listing of positions
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'per_page']);
        $positions = $this->positionService->getAllPositions($filters);

        return response()->json($positions);
    }

    /**
     * Store a newly created position
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        try {
            $position = $this->positionService->createPosition($request->validated());

            $this->logService->log('created', 'positions', $position->id, "Tạo chức vụ: {$position->name}");

            return response()->json([
                'message' => 'Tạo chức vụ thành công!',
                'data' => $position
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified position
     */
    public function show(int $id): JsonResponse
    {
        try {
            $position = $this->positionService->getPositionById($id);

            return response()->json([
                'data' => $position
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Chức vụ không tồn tại!'
            ], 404);
        }
    }

    /**
     * Update the specified position
     */
    public function update(UpdatePositionRequest $request, int $id): JsonResponse
    {
        try {
            $position = $this->positionService->updatePosition($id, $request->validated());

            $this->logService->log('updated', 'positions', $position->id, "Cập nhật chức vụ: {$position->name}");

            return response()->json([
                'message' => 'Cập nhật chức vụ thành công!',
                'data' => $position
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified position
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->positionService->deletePosition($id);

            $this->logService->log('deleted', 'positions', $id, "Xóa chức vụ ID: {$id}");

            return response()->json([
                'message' => 'Xóa chức vụ thành công!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get position statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->positionService->getPositionStatistics();

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Get position employees
     */
    public function employees(int $id): JsonResponse
    {
        try {
            $position = $this->positionService->getPositionById($id);

            return response()->json([
                'data' => [
                    'position' => $position,
                    'employees' => $position->employees
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Chức vụ không tồn tại!'
            ], 404);
        }
    }
}
