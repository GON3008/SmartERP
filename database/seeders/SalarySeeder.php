<?php

namespace Database\Seeders;

use App\Models\Salary;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SalarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::all();
        $currentYear = date('Y');
        $currentMonth = date('n');

        // Create salary records for the last 6 months
        foreach ($employees as $employee) {
            for ($i = 5; $i >= 0; $i--) {
                $month = $currentMonth - $i;
                $year = $currentYear;

                // Adjust year if month goes negative
                if ($month <= 0) {
                    $month += 12;
                    $year--;
                }

                // Base salary based on position
                $baseSalary = match($employee->position->name ?? 'Staff') {
                    'Director' => rand(30000000, 50000000),
                    'Manager' => rand(20000000, 35000000),
                    'Supervisor' => rand(15000000, 25000000),
                    'Team Leader' => rand(12000000, 20000000),
                    'Senior Staff' => rand(10000000, 15000000),
                    'Staff' => rand(7000000, 12000000),
                    'Junior Staff' => rand(5000000, 8000000),
                    'Intern' => rand(3000000, 5000000),
                    default => 8000000,
                };

                $allowance = rand(500000, 3000000);
                $deduction = rand(0, 1000000);
                $totalSalary = $baseSalary + $allowance - $deduction;

                Salary::create([
                    'employee_id' => $employee->id,
                    'base_salary' => $baseSalary,
                    'allowance' => $allowance,
                    'deduction' => $deduction,
                    'total_salary' => $totalSalary,
                    'month' => $month,
                    'year' => $year,
                ]);
            }
        }
    }
}
