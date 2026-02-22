<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salary\StoreSalaryRequest;
use App\Services\SalaryService;
use App\Models\Salary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    protected SalaryService $salaryService;

    public function __construct(SalaryService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    /**
     * Get all salary records with filters
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'month'       => 'nullable|integer|min:1|max:12',
            'year'        => 'nullable|integer|min:2000',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $query = Salary::with(['employee.department', 'employee.position'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->month) {
            $query->where('month', $request->month);
        }
        if ($request->year) {
            $query->where('year', $request->year);
        }

        $perPage = $request->per_page ?? 15;
        $salaries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $salaries->items(),
            'meta'    => [
                'total'        => $salaries->total(),
                'per_page'     => $salaries->perPage(),
                'current_page' => $salaries->currentPage(),
                'last_page'    => $salaries->lastPage(),
            ],
        ]);
    }

    /**
     * Get salary by ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $salary = $this->salaryService->getSalaryById($id);

            return response()->json([
                'success' => true,
                'data'    => $salary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Salary not found',
                'error'   => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Calculate and create salary
     */
    public function store(StoreSalaryRequest $request): JsonResponse
    {
        try {
            $salary = $this->salaryService->calculateSalary(
                $request->employee_id,
                $request->month,
                $request->year,
                $request->only(['base_salary', 'allowance', 'deduction'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Salary calculated successfully',
                'data'    => $salary,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate salary',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update salary
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'base_salary' => 'nullable|numeric|min:0',
            'allowance'   => 'nullable|numeric|min:0',
            'deduction'   => 'nullable|numeric|min:0',
        ]);

        try {
            $salary = $this->salaryService->updateSalary($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Salary updated successfully',
                'data'    => $salary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update salary',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete salary
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->salaryService->deleteSalary($id);

            return response()->json([
                'success' => true,
                'message' => 'Salary deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete salary',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get salary summary for a period
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2000',
        ]);

        try {
            $summary = $this->salaryService->getSalarySummary(
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
                'message' => 'Failed to get salary summary',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get salaries for an employee
     */
    public function employeeSalaries(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'limit'       => 'nullable|integer|min:1',
        ]);

        try {
            $salaries = $this->salaryService->getEmployeeSalaries(
                $request->employee_id,
                $request->limit
            );

            return response()->json([
                'success' => true,
                'data'    => $salaries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get employee salaries',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate payroll for all (or selected) employees
     */
    public function generatePayroll(Request $request): JsonResponse
    {
        $request->validate([
            'month'          => 'required|integer|min:1|max:12',
            'year'           => 'required|integer|min:2000',
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        try {
            $result = $this->salaryService->generatePayroll(
                $request->month,
                $request->year,
                $request->employee_ids ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Payroll generated successfully',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payroll',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get yearly salary statistics
     */
    public function yearlyStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000',
        ]);

        try {
            $statistics = $this->salaryService->getYearlySalaryStatistics($request->year);

            return response()->json([
                'success' => true,
                'data'    => $statistics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get yearly statistics',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get top earners
     */
    public function topEarners(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2000',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $topEarners = $this->salaryService->getTopEarners(
                $request->month,
                $request->year,
                $request->limit ?? 10
            );

            return response()->json([
                'success' => true,
                'data'    => $topEarners,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get top earners',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
