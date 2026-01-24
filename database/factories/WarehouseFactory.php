<?php

namespace Database\Factories;

use App\Models\WareHouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = [
            'Hanoi - Hai Ba Trung',
            'Hanoi - Ba Dinh',
            'Ho Chi Minh - Quan 1',
            'Ho Chi Minh - Thu Duc',
            'Da Nang - Hoa Khanh',
            'Hai Phong - Le Chan'
        ];
        return [
            'name' => 'Warehouse ' . fake()->unique()->city(),
            'location' => fake()->randomElement($locations),
        ];
    }
}
