<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Department;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = [
            'Sales & Marketing',
            'Production',
            'Warehouse & Logistics',
            'Human Resources',
            'Finance & Accounting',
            'IT & Technology',
            'Quality Control',
            'Research & Development'
        ];
        return [
            'name' => fake()->unique()->randomElement($departments),
            'description' => fake()->paragraph(),
        ];
    }
}
