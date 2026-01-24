<?php

namespace Database\Seeders;

use App\Models\InventoryRecommendation;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InventoryRecommendationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::inRandomOrder()->limit(30)->get();

        foreach ($products as $product) {
            $avgDailySales = fake()->randomFloat(2, 1, 50);
            $forecastDays = fake()->randomElement([7, 14, 30]);
            $recommendedQuantity = (int) ceil($avgDailySales * $forecastDays * 1.2); // 20% buffer

            InventoryRecommendation::create([
                'product_id' => $product->id,
                'avg_daily_sales' => $avgDailySales,
                'forecast_days' => $forecastDays,
                'recommended_quantity' => $recommendedQuantity,
                'ai_summary' => "Based on historical sales data, we recommend stocking {$recommendedQuantity} units for the next {$forecastDays} days.",
            ]);
        }
    }
}
