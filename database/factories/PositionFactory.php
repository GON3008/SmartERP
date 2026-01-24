<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Position;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $positions = [
            'Manager',
            'Supervisor',
            'Team Leader',
            'Senior Staff',
            'Staff',
            'Junior Staff',
            'Intern'
        ];
        return [
            'name' => fake()->unique()->randomElement($positions),
        ];
    }
}
