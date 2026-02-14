<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Services\AttendanceService;
use App\Http\Resources\AttendanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Record attendance manually
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        try {
            $attendance = $this->attendanceService->recordAttendance($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => new AttendanceResource($attendance),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record attendance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check in
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'time' => 'nullable|date_format:H:i:s',
        ]);

        try {
            $attendance = $this->attendanceService->checkIn(
                $request->employee_id,
                $request->time
            );

            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully',
                'data' => new AttendanceResource($attendance),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check in',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check out
     */
    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'time' => 'nullable|date_format:H:i:s',
        ]);

        try {
            $attendance = $this->attendanceService->checkOut(
                $request->employee_id,
                $request->time
            );

            return response()->json([
                'success' => true,
                'message' => 'Checked out successfully',
                'data' => new AttendanceResource($attendance),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check out',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get attendance report for an employee
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000',
        ]);

        try {
            $report = $this->attendanceService->getAttendanceReport(
                $request->employee_id,
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data' => new AttendanceResource($report),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get attendance report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get monthly attendance summary for all employees
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000',
        ]);

        try {
            $summary = $this->attendanceService->getMonthlyAttendanceSummary(
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data' => new AttendanceResource($summary),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get monthly summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employees who are late frequently
     */
    public function lateEmployees(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000',
            'threshold' => 'nullable|integer|min:1',
        ]);

        try {
            $lateEmployees = $this->attendanceService->getLateEmployees(
                $request->month,
                $request->year,
                $request->threshold ?? 3
            );

            return response()->json([
                'success' => true,
                'data' => new AttendanceResource($lateEmployees),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get late employees',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance by date range
     */
    public function dateRange(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $attendances = $this->attendanceService->getAttendanceByDateRange(
                $request->employee_id,
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'data' => new AttendanceResource($attendances),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get attendance records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk import attendance records
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'records' => 'required|array',
            'records.*.employee_id' => 'required|exists:employees,id',
            'records.*.date' => 'required|date',
            'records.*.check_in' => 'nullable|date_format:H:i:s',
            'records.*.check_out' => 'nullable|date_format:H:i:s',
        ]);

        try {
            $result = $this->attendanceService->bulkImportAttendance($request->records);

            return response()->json([
                'success' => true,
                'message' => 'Bulk import completed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import attendance records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get today's attendance status
     */
    public function todayStatus(): JsonResponse
    {
        try {
            $status = $this->attendanceService->getTodayAttendanceStatus();

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get today\'s status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate overtime hours
     */
    public function overtime(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000',
        ]);

        try {
            $overtimeHours = $this->attendanceService->calculateOvertimeHours(
                $request->employee_id,
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'employee_id' => $request->employee_id,
                    'month' => $request->month,
                    'year' => $request->year,
                    'overtime_hours' => $overtimeHours,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate overtime',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
