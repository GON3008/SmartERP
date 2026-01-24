<?php

namespace Database\Factories;

use App\Models\StockIn;
use App\Models\Product;
use App\Models\WareHouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockIn>
 */
class StockInFactory extends Factory
{
    protected $model = StockIn::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::inRandomOrder()->first()?->id ?? Product::factory(),
            'warehouse_id' => Warehouse::inRandomOrder()->first()?->id ?? Warehouse::factory(),
            'quantity' => fake()->numberBetween(10, 500),
            'import_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'note' => fake()->optional(0.5)->sentence(),
        ];
    }
}
