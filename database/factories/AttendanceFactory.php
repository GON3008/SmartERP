<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->time('H:i:s', '09:30:00');
        $checkOut = fake()->optional(0.9)->time('H:i:s', '18:00:00');
        return [
            'employee_id' => Employee::inRandomOrder()->first()?->id ?? Employee::factory(),
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ];
    }

    public function late(): static
    {
        return $this->state(fn(array $attributes) => [
            'check_in' => fake()->time('H:i:s', '08:00:00'),
        ]);
    }

    public function noCheckOut(): static
    {
        return $this->state(fn(array $attributes) => [
            'check_out' => null,
        ]);
    }
}
