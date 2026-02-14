<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DepartmentService;
use App\Services\ActivityLogService;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    protected $departmentService;
    protected $logService;

    public function __construct(DepartmentService $departmentService, ActivityLogService $logService)
    {
        $this->departmentService = $departmentService;
        $this->logService = $logService;
    }

    /**
     * Display a listing of departments
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'per_page']);
        $departments = $this->departmentService->getAllDepartments($filters);

        return response()->json([
            'success' => true,
            'data' => DepartmentResource::collection($departments)
        ]);
    }

    /**
     * Store a newly created department
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        try {
            $department = $this->departmentService->createDepartment($request->validated());

            $this->logService->log('created', 'departments', $department->id, "Tạo phòng ban: {$department->name}");

            return response()->json([
                'success' => true,
                'message' => 'Tạo phòng ban thành công!',
                'data' => new DepartmentResource($department)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified department
     */
    public function show(int $id): JsonResponse
    {
        try {
            $department = $this->departmentService->getDepartmentById($id);

            return response()->json([
                'success' => true,
                'data' => new DepartmentResource($department)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Phòng ban không tồn tại!'
            ], 404);
        }
    }

    /**
     * Update the specified department
     */
    public function update(UpdateDepartmentRequest $request, int $id): JsonResponse
    {
        try {
            $department = $this->departmentService->updateDepartment($id, $request->validated());

            $this->logService->log('updated', 'departments', $department->id, "Cập nhật phòng ban: {$department->name}");

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phòng ban thành công!',
                'data' => new DepartmentResource($department)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified department
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->departmentService->deleteDepartment($id);

            $this->logService->log('deleted', 'departments', $id, "Xóa phòng ban ID: {$id}");

            return response()->json([
                'success' => true,
                'message' => 'Xóa phòng ban thành công!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get department statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->departmentService->getDepartmentStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get department employees
     */
    public function employees(int $id): JsonResponse
    {
        try {
            $department = $this->departmentService->getDepartmentById($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'department' => new DepartmentResource($department),
                    'employees' => \App\Http\Resources\EmployeeResource::collection($department->employees)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Phòng ban không tồn tại!'
            ], 404);
        }
    }
}
