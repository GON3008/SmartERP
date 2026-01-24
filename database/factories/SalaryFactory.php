<?php

namespace Database\Factories;

use App\Models\Salary;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Salary>
 */
class SalaryFactory extends Factory
{
    protected $model = Salary::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseSalary = fake()->randomFloat(2, 5000000, 30000000); // 5M - 30M VND
        $allowance = fake()->randomFloat(2, 500000, 3000000);
        $deduction = fake()->randomFloat(2, 0, 1000000);
        $totalSalary = $baseSalary + $allowance - $deduction;

        return [
            'employee_id' => Employee::inRandomOrder()->first()?->id ?? Employee::factory(),
            'base_salary' => $baseSalary,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'total_salary' => $totalSalary,
            'month' => fake()->numberBetween(1, 12),
            'year' => fake()->numberBetween(2023, 2025),
        ];
    }
}
