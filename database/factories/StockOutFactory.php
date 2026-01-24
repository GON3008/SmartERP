<?php

namespace Database\Factories;

use App\Models\StockOut;
use App\Models\Product;
use App\Models\WareHouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockOut>
 */
class StockOutFactory extends Factory
{
    protected $model = StockOut::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasons = ['Sale', 'Production', 'Damaged', 'Return', 'Transfer', 'Sample'];
        return [
            'product_id' => Product::inRandomOrder()->first()?->id ?? Product::factory(),
            'warehouse_id' => Warehouse::inRandomOrder()->first()?->id ?? Warehouse::factory(),
            'quantity' => fake()->numberBetween(1, 100),
            'export_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'reason' => fake()->randomElement($reasons),
        ];
    }
}
