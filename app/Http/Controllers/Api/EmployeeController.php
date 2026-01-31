<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmployeeService;
use App\Services\ActivityLogService;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Salary\StoreSalaryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    protected $employeeService;
    protected $logService;

    public function __construct(EmployeeService $employeeService, ActivityLogService $logService)
    {
        $this->employeeService = $employeeService;
        $this->logService = $logService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'department_id', 'position_id', 'status', 'per_page']);
        $employees = $this->employeeService->getAllEmployees($filters);

        return response()->json($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $employee = $this->employeeService->createEmployee($request->validated());
            $this->logService->log('created', 'employees', $employee->id, "Tạo NV: {$employee->full_name}");

            return response()->json(['message' => 'Tạo nhân viên thành công!', 'data' => $employee], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);
            return response()->json(['data' => $employee]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Nhân viên không tồn tại!'], 404);
        }
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->updateEmployee($id, $request->validated());
            return response()->json(['message' => 'Cập nhật thành công!', 'data' => $employee]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->employeeService->deleteEmployee($id);
            return response()->json(['message' => 'Xóa nhân viên thành công!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'required|exists:employees,id']);

        try {
            $attendance = $this->employeeService->checkIn($request->employee_id);
            return response()->json(['message' => 'Check in thành công!', 'data' => $attendance]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function checkOut(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'required|exists:employees,id']);

        try {
            $attendance = $this->employeeService->checkOut($request->employee_id);
            return response()->json(['message' => 'Check out thành công!', 'data' => $attendance]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }

    public function attendanceReport(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000',
        ]);

        $report = $this->employeeService->getAttendanceReport($id, $request->month, $request->year);
        return response()->json(['data' => $report]);
    }

    public function calculateSalary(StoreSalaryRequest $request): JsonResponse
    {
        try {
            $salary = $this->employeeService->calculateSalary(
                $request->employee_id,
                $request->month,
                $request->year,
                $request->validated()
            );

            return response()->json(['message' => 'Tính lương thành công!', 'data' => $salary], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 422);
        }
    }
}
