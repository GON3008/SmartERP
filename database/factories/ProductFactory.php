<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['Electronics', 'Furniture', 'Food & Beverage', 'Textiles', 'Raw Materials', 'Packaging'];
        $units = ['pcs', 'kg', 'liter', 'box', 'meter', 'dozen'];
        return [
            'sku' => 'PRD' . fake()->unique()->numberBetween(10000, 99999),
            'name' => fake()->words(3, true) . ' ' . fake()->randomElement(['Product', 'Item', 'Material']),
            'category' => fake()->randomElement($categories),
            'unit' => fake()->randomElement($units),
            'price' => fake()->randomFloat(2, 10000, 5000000),
            'min_stock' => fake()->numberBetween(10, 100),
        ];
    }

    public function rawMaterial(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => 'Raw Materials',
        ]);
    }

    public function finishedGood(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => fake()->randomElement(['Electronics', 'Furniture', 'Food & Beverage', 'Textiles']),
        ]);
    }
}
