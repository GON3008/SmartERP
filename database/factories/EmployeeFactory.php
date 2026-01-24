<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use App\Models\Position;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'position_id' => Position::inRandomOrder()->first()?->id ?? Position::factory(),
            'department_id' => Department::inRandomOrder()->first()?->id ?? Department::factory(),
            'employee_code' => 'EMP' . fake()->unique()->numberBetween(1000, 9999),
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'email' => fake()->unique()->safeEmail(),
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'status' => fake()->boolean(90), // 90% active
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
