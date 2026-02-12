<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Record attendance manually
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
    public function checkIn(int $employeeId, ?string $time = null): Attendance
    {
        $today = \Carbon\Carbon::today();

        $attendance = Attendance::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'date' => $today,
            ],
            [
                'check_in' => $time ?? \Carbon\Carbon::now()->format('H:i:s'),
            ]
        );

        if ($attendance->wasRecentlyCreated) {
            return $attendance;
        }

        // Update if already exists but no check_in yet
        if (!$attendance->check_in) {
            $attendance->update(['check_in' => $time ?? \Carbon\Carbon::now()->format('H:i:s')]);
        }

        return $attendance->fresh();
    }

    /**
     * Check out
     */
    public function checkOut(int $employeeId, ?string $time = null): Attendance
    {
        $today = \Carbon\Carbon::today();

        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->firstOrFail();

        $attendance->update([
            'check_out' => $time ?? \Carbon\Carbon::now()->format('H:i:s')
        ]);

        return $attendance->fresh();
    }

    /**
     * Get attendance report for an employee
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
        $earlyDepartures = 0;
        $totalHours = 0;
        $overtimeHours = 0;

        foreach ($attendances as $attendance) {
            $workingDays++;

            // Check late arrival (after 08:30)
            if ($attendance->check_in && \Carbon\Carbon::parse($attendance->check_in)->format('H:i') > '08:30') {
                $lateDays++;
            }

            // Check early departure (before 17:30)
            if ($attendance->check_out && \Carbon\Carbon::parse($attendance->check_out)->format('H:i') < '17:30') {
                $earlyDepartures++;
            }

            // Calculate work hours
            if ($attendance->check_in && $attendance->check_out) {
                $checkIn = \Carbon\Carbon::parse($attendance->check_in);
                $checkOut = \Carbon\Carbon::parse($attendance->check_out);
                $hours = $checkOut->diffInHours($checkIn);
                $totalHours += $hours;

                // Calculate overtime (more than 8 hours)
                if ($hours > 8) {
                    $overtimeHours += ($hours - 8);
                }
            }
        }

        return [
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'working_days' => $workingDays,
            'late_days' => $lateDays,
            'early_departures' => $earlyDepartures,
            'total_hours' => $totalHours,
            'overtime_hours' => $overtimeHours,
            'average_hours_per_day' => $workingDays > 0 ? round($totalHours / $workingDays, 2) : 0,
            'attendances' => $attendances,
        ];
    }

    /**
     * Calculate work hours for a specific date
     */
    public function calculateWorkHours(int $employeeId, string $date): float
    {
        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->first();

        if (!$attendance || !$attendance->check_in || !$attendance->check_out) {
            return 0;
        }

        $checkIn = \Carbon\Carbon::parse($attendance->check_in);
        $checkOut = \Carbon\Carbon::parse($attendance->check_out);

        return $checkOut->diffInHours($checkIn, true);
    }

    /**
     * Calculate overtime hours for a month
     */
    public function calculateOvertimeHours(int $employeeId, int $month, int $year): float
    {
        $attendances = Attendance::where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $overtimeHours = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->check_in && $attendance->check_out) {
                $checkIn = \Carbon\Carbon::parse($attendance->check_in);
                $checkOut = \Carbon\Carbon::parse($attendance->check_out);
                $hours = $checkOut->diffInHours($checkIn, true);

                // Overtime is hours worked beyond 8 hours
                if ($hours > 8) {
                    $overtimeHours += ($hours - 8);
                }
            }
        }

        return round($overtimeHours, 2);
    }

    /**
     * Get monthly attendance summary for all employees
     */
    public function getMonthlyAttendanceSummary(int $month, int $year): array
    {
        $employees = \App\Models\Employee::with(['attendances' => function ($q) use ($month, $year) {
            $q->whereYear('date', $year)
                ->whereMonth('date', $month);
        }])->get();

        $summary = [];

        foreach ($employees as $employee) {
            $report = $this->getAttendanceReport($employee->id, $month, $year);
            $summary[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'working_days' => $report['working_days'],
                'late_days' => $report['late_days'],
                'early_departures' => $report['early_departures'],
                'total_hours' => $report['total_hours'],
                'overtime_hours' => $report['overtime_hours'],
            ];
        }

        return [
            'month' => $month,
            'year' => $year,
            'total_employees' => count($summary),
            'summary' => $summary,
        ];
    }

    /**
     * Get employees who are late frequently
     */
    public function getLateEmployees(int $month, int $year, int $threshold = 3): array
    {
        $employees = \App\Models\Employee::all();
        $lateEmployees = [];

        foreach ($employees as $employee) {
            $report = $this->getAttendanceReport($employee->id, $month, $year);

            if ($report['late_days'] >= $threshold) {
                $lateEmployees[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'late_days' => $report['late_days'],
                    'working_days' => $report['working_days'],
                    'late_percentage' => round(($report['late_days'] / $report['working_days']) * 100, 2),
                ];
            }
        }

        return $lateEmployees;
    }

    /**
     * Get attendance by date range
     */
    public function getAttendanceByDateRange(int $employeeId, string $startDate, string $endDate)
    {
        return Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();
    }

    /**
     * Bulk import attendance records
     */
    public function bulkImportAttendance(array $records): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($records as $record) {
                $existing = Attendance::where('employee_id', $record['employee_id'])
                    ->whereDate('date', $record['date'])
                    ->first();

                if ($existing) {
                    $existing->update($record);
                    $updated++;
                } else {
                    Attendance::create($record);
                    $created++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $errors[] = $e->getMessage();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Get today's attendance status
     */
    public function getTodayAttendanceStatus(): array
    {
        $today = \Carbon\Carbon::today();
        $totalEmployees = \App\Models\Employee::where('status', true)->count();

        $checkedIn = Attendance::whereDate('date', $today)
            ->whereNotNull('check_in')
            ->count();

        $checkedOut = Attendance::whereDate('date', $today)
            ->whereNotNull('check_out')
            ->count();

        $notCheckedIn = $totalEmployees - $checkedIn;

        return [
            'date' => $today->format('Y-m-d'),
            'total_employees' => $totalEmployees,
            'checked_in' => $checkedIn,
            'checked_out' => $checkedOut,
            'not_checked_in' => $notCheckedIn,
            'attendance_rate' => $totalEmployees > 0 ? round(($checkedIn / $totalEmployees) * 100, 2) : 0,
        ];
    }
}
