<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Services\AttendanceService;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
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
     * Get all attendance records with filters
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'month'       => 'nullable|integer|min:1|max:12',
            'year'        => 'nullable|integer|min:2000',
            'date'        => 'nullable|date',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $query = Attendance::with('employee')
            ->orderBy('date', 'desc');

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->month) {
            $query->whereMonth('date', $request->month);
        }
        if ($request->year) {
            $query->whereYear('date', $request->year);
        }
        if ($request->date) {
            $query->whereDate('date', $request->date);
        }

        $perPage = $request->per_page ?? 20;
        $attendances = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => AttendanceResource::collection($attendances),
            'meta'    => [
                'total'        => $attendances->total(),
                'per_page'     => $attendances->perPage(),
                'current_page' => $attendances->currentPage(),
                'last_page'    => $attendances->lastPage(),
            ],
        ]);
    }

    /**
     * Get single attendance record
     */
    public function show(int $id): JsonResponse
    {
        try {
            $attendance = Attendance::with('employee')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => new AttendanceResource($attendance),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found',
            ], 404);
        }
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
                'data'    => new AttendanceResource($attendance),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record attendance',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update attendance record
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'check_in'  => 'nullable|date_format:H:i:s',
            'check_out' => 'nullable|date_format:H:i:s',
            'status'    => 'nullable|string|in:present,absent,late,half_day',
            'notes'     => 'nullable|string|max:500',
        ]);

        try {
            $attendance = Attendance::findOrFail($id);
            $attendance->update($request->only(['check_in', 'check_out', 'status', 'notes']));

            return response()->json([
                'success' => true,
                'message' => 'Attendance updated successfully',
                'data'    => new AttendanceResource($attendance->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete attendance record
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $attendance = Attendance::findOrFail($id);
            $attendance->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attendance deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance',
                'error'   => $e->getMessage(),
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
            'time'        => 'nullable|date_format:H:i:s',
        ]);

        try {
            $attendance = $this->attendanceService->checkIn(
                $request->employee_id,
                $request->time
            );

            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully',
                'data'    => new AttendanceResource($attendance),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check in',
                'error'   => $e->getMessage(),
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
            'time'        => 'nullable|date_format:H:i:s',
        ]);

        try {
            $attendance = $this->attendanceService->checkOut(
                $request->employee_id,
                $request->time
            );

            return response()->json([
                'success' => true,
                'message' => 'Checked out successfully',
                'data'    => new AttendanceResource($attendance),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check out',
                'error'   => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get monthly attendance summary for all employees
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2000',
        ]);

        try {
            $summary = $this->attendanceService->getMonthlyAttendanceSummary(
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data'    => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get monthly summary',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get today's attendance status (company-wide)
     */
    public function todayStatus(): JsonResponse
    {
        try {
            $status = $this->attendanceService->getTodayAttendanceStatus();

            return response()->json([
                'success' => true,
                'data'    => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to get today's status",
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance report for an employee
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month'       => 'required|integer|min:1|max:12',
            'year'        => 'required|integer|min:2000',
        ]);

        try {
            $report = $this->attendanceService->getAttendanceReport(
                $request->employee_id,
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data'    => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get attendance report',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employees who are late frequently
     */
    public function lateEmployees(Request $request): JsonResponse
    {
        $request->validate([
            'month'     => 'required|integer|min:1|max:12',
            'year'      => 'required|integer|min:2000',
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
                'data'    => $lateEmployees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get late employees',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate overtime hours for an employee
     */
    public function overtime(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month'       => 'required|integer|min:1|max:12',
            'year'        => 'required|integer|min:2000',
        ]);

        try {
            $overtimeHours = $this->attendanceService->calculateOvertimeHours(
                $request->employee_id,
                $request->month,
                $request->year
            );

            return response()->json([
                'success' => true,
                'data'    => [
                    'employee_id'    => $request->employee_id,
                    'month'          => $request->month,
                    'year'           => $request->year,
                    'overtime_hours' => $overtimeHours,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate overtime',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
