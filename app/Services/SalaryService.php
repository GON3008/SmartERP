<?php

namespace App\Services;

use App\Models\Salary;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    protected AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Calculate and create salary for an employee
     */
    public function calculateSalary(int $employeeId, int $month, int $year, array $data = []): Salary
    {
        $employee = Employee::with('position')->findOrFail($employeeId);

        // Get attendance report for the month
        $attendanceReport = $this->attendanceService->getAttendanceReport($employeeId, $month, $year);

        // Base salary from data or default
        $baseSalary = $data['base_salary'] ?? 10000000; // Default 10M VND

        // Calculate allowances
        $allowance = $this->calculateAllowances($attendanceReport, $data);

        // Calculate deductions
        $deduction = $this->calculateDeductions($attendanceReport, $data);

        // Calculate overtime pay
        $overtimePay = $this->calculateOvertimePay($attendanceReport, $baseSalary);

        // Total salary
        $totalSalary = $baseSalary + $allowance + $overtimePay - $deduction;

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
     * Calculate allowances based on attendance
     */
    protected function calculateAllowances(array $attendanceReport, array $data = []): float
    {
        $allowance = $data['allowance'] ?? 0;

        // Full attendance bonus (>= 22 working days)
        if ($attendanceReport['working_days'] >= 22) {
            $allowance += 1000000; // 1M VND bonus
        }

        // Perfect attendance bonus (no late days)
        if ($attendanceReport['late_days'] == 0 && $attendanceReport['working_days'] >= 20) {
            $allowance += 500000; // 500K VND bonus
        }

        return $allowance;
    }

    /**
     * Calculate deductions based on attendance
     */
    protected function calculateDeductions(array $attendanceReport, array $data = []): float
    {
        $deduction = $data['deduction'] ?? 0;

        // Late deduction (50K per late day)
        $lateDeduction = $attendanceReport['late_days'] * 50000;
        $deduction += $lateDeduction;

        // Early departure deduction (30K per early departure)
        $earlyDeduction = ($attendanceReport['early_departures'] ?? 0) * 30000;
        $deduction += $earlyDeduction;

        // Absent deduction (if working days < expected)
        $expectedWorkingDays = 22; // Standard working days per month
        if ($attendanceReport['working_days'] < $expectedWorkingDays) {
            $absentDays = $expectedWorkingDays - $attendanceReport['working_days'];
            $absentDeduction = $absentDays * 200000; // 200K per absent day
            $deduction += $absentDeduction;
        }

        return $deduction;
    }

    /**
     * Calculate overtime pay
     */
    protected function calculateOvertimePay(array $attendanceReport, float $baseSalary): float
    {
        $overtimeHours = $attendanceReport['overtime_hours'] ?? 0;

        if ($overtimeHours <= 0) {
            return 0;
        }

        // Calculate hourly rate (base salary / 176 hours per month)
        $hourlyRate = $baseSalary / 176;

        // Overtime rate is 1.5x for normal overtime
        $overtimeRate = $hourlyRate * 1.5;

        return $overtimeHours * $overtimeRate;
    }

    /**
     * Get salary by ID
     */
    public function getSalaryById(int $id): Salary
    {
        return Salary::with('employee')->findOrFail($id);
    }

    /**
     * Get salaries for an employee
     */
    public function getEmployeeSalaries(int $employeeId, ?int $limit = null)
    {
        $query = Salary::where('employee_id', $employeeId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($limit) {
            return $query->limit($limit)->get();
        }

        return $query->get();
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
            'highest_salary' => $salaries->max('total_salary'),
            'lowest_salary' => $salaries->min('total_salary'),
            'details' => $salaries,
        ];
    }

    /**
     * Generate payroll for all employees in a month
     */
    public function generatePayroll(int $month, int $year, array $employeeIds = []): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        // Get employees
        $query = Employee::query();
        if (!empty($employeeIds)) {
            $query->whereIn('id', $employeeIds);
        } else {
            $query->where('status', true); // Only active employees
        }

        $employees = $query->get();

        DB::beginTransaction();
        try {
            foreach ($employees as $employee) {
                // Check if salary already exists
                $existing = Salary::where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                if ($existing) {
                    $results['failed'][] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'reason' => 'Salary already exists for this period',
                    ];
                    continue;
                }

                try {
                    $salary = $this->calculateSalary($employee->id, $month, $year);
                    $results['success'][] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'total_salary' => $salary->total_salary,
                    ];
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * Update salary
     */
    public function updateSalary(int $id, array $data): Salary
    {
        $salary = Salary::findOrFail($id);

        // Recalculate total if components changed
        if (isset($data['base_salary']) || isset($data['allowance']) || isset($data['deduction'])) {
            $baseSalary = $data['base_salary'] ?? $salary->base_salary;
            $allowance = $data['allowance'] ?? $salary->allowance;
            $deduction = $data['deduction'] ?? $salary->deduction;

            $data['total_salary'] = $baseSalary + $allowance - $deduction;
        }

        $salary->update($data);
        return $salary->fresh();
    }

    /**
     * Delete salary
     */
    public function deleteSalary(int $id): bool
    {
        $salary = Salary::findOrFail($id);
        return $salary->delete();
    }

    /**
     * Get salary statistics for a year
     */
    public function getYearlySalaryStatistics(int $year): array
    {
        $salaries = Salary::whereYear('year', $year)
            ->with('employee')
            ->get();

        $byMonth = $salaries->groupBy('month')->map(function ($monthSalaries) {
            return [
                'total_employees' => $monthSalaries->count(),
                'total_salary' => $monthSalaries->sum('total_salary'),
                'average_salary' => $monthSalaries->avg('total_salary'),
            ];
        });

        return [
            'year' => $year,
            'total_paid' => $salaries->sum('total_salary'),
            'average_monthly_payroll' => $salaries->groupBy('month')->avg(function ($monthSalaries) {
                return $monthSalaries->sum('total_salary');
            }),
            'by_month' => $byMonth,
        ];
    }

    /**
     * Get top earners for a period
     */
    public function getTopEarners(int $month, int $year, int $limit = 10): array
    {
        return Salary::with('employee')
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('total_salary', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($salary) {
                return [
                    'employee_id' => $salary->employee_id,
                    'employee_name' => $salary->employee->full_name,
                    'employee_code' => $salary->employee->employee_code,
                    'department' => $salary->employee->department->name ?? 'N/A',
                    'position' => $salary->employee->position->name ?? 'N/A',
                    'total_salary' => $salary->total_salary,
                ];
            })
            ->toArray();
    }

    /**
     * Compare salary between two periods
     */
    public function compareSalaryPeriods(int $month1, int $year1, int $month2, int $year2): array
    {
        $period1 = $this->getSalarySummary($month1, $year1);
        $period2 = $this->getSalarySummary($month2, $year2);

        $totalDifference = $period2['total_salary'] - $period1['total_salary'];
        $percentageChange = $period1['total_salary'] > 0
            ? round(($totalDifference / $period1['total_salary']) * 100, 2)
            : 0;

        return [
            'period_1' => [
                'month' => $month1,
                'year' => $year1,
                'total_salary' => $period1['total_salary'],
                'total_employees' => $period1['total_employees'],
            ],
            'period_2' => [
                'month' => $month2,
                'year' => $year2,
                'total_salary' => $period2['total_salary'],
                'total_employees' => $period2['total_employees'],
            ],
            'difference' => $totalDifference,
            'percentage_change' => $percentageChange,
        ];
    }
}
