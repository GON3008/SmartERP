<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Salary;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeService
{
    /**
     * Get all employees
     */
    public function getAllEmployees(array $filters = [])
    {
        $query = Employee::query();

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by department
        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        // Filter by position
        if (!empty($filters['position_id'])) {
            $query->where('position_id', $filters['position_id']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with(['user', 'department', 'position'])
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get employee by ID
     */
    public function getEmployeeById(int $id)
    {
        return Employee::with([
            'user',
            'department',
            'position',
            'attendances' => function ($q) {
                $q->orderBy('date', 'desc')->limit(30);
            },
            'salaries' => function ($q) {
                $q->orderBy('year', 'desc')->orderBy('month', 'desc')->limit(6);
            }
        ])->findOrFail($id);
    }

    /**
     * Create employee
     */
    public function createEmployee(array $data): Employee
    {
        return Employee::create($data);
    }

    /**
     * Update employee
     */
    public function updateEmployee(int $id, array $data): Employee
    {
        $employee = Employee::findOrFail($id);
        $employee->update($data);
        return $employee->fresh(['user', 'department', 'position']);
    }

    /**
     * Delete employee
     */
    public function deleteEmployee(int $id): bool
    {
        $employee = Employee::findOrFail($id);
        return $employee->delete();
    }

    /**
     * Record attendance
     */
    public function recordAttendance(array $data): Attendance
    {
        // Check if attendance already exists
        $existing = Attendance::where('employee_id', $data['employee_id'])
            ->whereDate('date', $data['date'])
            ->first();

        if ($existing) {
            // Update existing
            $existing->update($data);
            return $existing;
        }

        return Attendance::create($data);
    }

    /**
     * Check in
     */
    public function checkIn(int $employeeId, string $time = null): Attendance
    {
        $today = Carbon::today();

        $attendance = Attendance::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'date' => $today,
            ],
            [
                'check_in' => $time ?? Carbon::now()->format('H:i:s'),
            ]
        );

        if ($attendance->wasRecentlyCreated) {
            return $attendance;
        }

        // Update if already exists but no check_in yet
        if (!$attendance->check_in) {
            $attendance->update(['check_in' => $time ?? Carbon::now()->format('H:i:s')]);
        }

        return $attendance->fresh();
    }

    /**
     * Check out
     */
    public function checkOut(int $employeeId, string $time = null): Attendance
    {
        $today = Carbon::today();

        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->firstOrFail();

        $attendance->update([
            'check_out' => $time ?? Carbon::now()->format('H:i:s')
        ]);

        return $attendance->fresh();
    }

    /**
     * Get attendance report
     */
    public function getAttendanceReport(int $employeeId, int $month, int $year): array
    {
        $attendances = Attendance::where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();

        $workingDays = 0;
        $lateDays = 0;
        $totalHours = 0;

        foreach ($attendances as $attendance) {
            $workingDays++;

            if ($attendance->check_in && Carbon::parse($attendance->check_in)->format('H:i') > '08:30') {
                $lateDays++;
            }

            if ($attendance->check_in && $attendance->check_out) {
                $checkIn = Carbon::parse($attendance->check_in);
                $checkOut = Carbon::parse($attendance->check_out);
                $totalHours += $checkOut->diffInHours($checkIn);
            }
        }

        return [
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'working_days' => $workingDays,
            'late_days' => $lateDays,
            'total_hours' => $totalHours,
            'average_hours_per_day' => $workingDays > 0 ? round($totalHours / $workingDays, 2) : 0,
            'attendances' => $attendances,
        ];
    }

    /**
     * Calculate and create salary
     */
    public function calculateSalary(int $employeeId, int $month, int $year, array $data = []): Salary
    {
        $employee = Employee::with('position')->findOrFail($employeeId);

        // Get attendance for the month
        $attendanceReport = $this->getAttendanceReport($employeeId, $month, $year);

        // Base salary from data or default
        $baseSalary = $data['base_salary'] ?? 10000000; // Default 10M

        // Calculate allowance based on working days
        $workingDayAllowance = ($attendanceReport['working_days'] >= 22) ? 1000000 : 0;
        $allowance = ($data['allowance'] ?? 0) + $workingDayAllowance;

        // Calculate deduction for late days
        $lateDeduction = $attendanceReport['late_days'] * 50000; // 50k per late day
        $deduction = ($data['deduction'] ?? 0) + $lateDeduction;

        // Total salary
        $totalSalary = $baseSalary + $allowance - $deduction;

        return Salary::create([
            'employee_id' => $employeeId,
            'base_salary' => $baseSalary,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'total_salary' => $totalSalary,
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * Get employee statistics
     */
    public function getEmployeeStatistics(): array
    {
        return [
            'total_employees' => Employee::count(),
            'active_employees' => Employee::where('status', true)->count(),
            'inactive_employees' => Employee::where('status', false)->count(),
            'by_department' => Employee::select('department_id', DB::raw('count(*) as count'))
                ->with('department:id,name')
                ->groupBy('department_id')
                ->get()
                ->map(fn($item) => [
                    'department' => $item->department->name ?? 'Unknown',
                    'count' => $item->count
                ]),
            'by_position' => Employee::select('position_id', DB::raw('count(*) as count'))
                ->with('position:id,name')
                ->groupBy('position_id')
                ->get()
                ->map(fn($item) => [
                    'position' => $item->position->name ?? 'Unknown',
                    'count' => $item->count
                ]),
        ];
    }

    /**
     * Get salary summary for a period
     */
    public function getSalarySummary(int $month, int $year): array
    {
        $salaries = Salary::with('employee')
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        return [
            'month' => $month,
            'year' => $year,
            'total_employees' => $salaries->count(),
            'total_base_salary' => $salaries->sum('base_salary'),
            'total_allowance' => $salaries->sum('allowance'),
            'total_deduction' => $salaries->sum('deduction'),
            'total_salary' => $salaries->sum('total_salary'),
            'average_salary' => $salaries->avg('total_salary'),
            'details' => $salaries,
        ];
    }
}
