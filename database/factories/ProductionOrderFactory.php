<?php

namespace Database\Factories;

use App\Models\ProductionOrder;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    protected $model = ProductionOrder::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        $status = fake()->randomElement($statuses);

        $startDate = null;
        $endDate = null;

        if (in_array($status, ['in_progress', 'completed'])) {
            $startDate = fake()->dateTimeBetween('-2 months', '-1 week');
            if ($status === 'completed') {
                $endDate = fake()->dateTimeBetween($startDate, 'now');
            }
        }
        return [
            'order_code' => 'PRO' . fake()->unique()->numberBetween(100000, 999999),
            'product_id' => Product::inRandomOrder()->first()?->id ?? Product::factory(),
            'quantity' => fake()->numberBetween(10, 1000),
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'start_date' => fake()->dateTimeBetween('-2 months', '-1 week'),
            'end_date' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
