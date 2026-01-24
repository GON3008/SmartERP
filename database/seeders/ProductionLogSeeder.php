<?php

namespace Database\Seeders;

use App\Models\ProductionLog;
use App\Models\ProductionOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductionLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productionOrders = ProductionOrder::whereIn('status', ['in_progress', 'completed'])->get();

        foreach ($productionOrders as $order) {
            // Create 1-3 logs per order
            $numLogs = rand(1, 3);

            for ($i = 0; $i < $numLogs; $i++) {
                ProductionLog::create([
                    'production_order_id' => $order->id,
                    'note' => fake()->sentence(10),
                ]);
            }
        }
    }
}
