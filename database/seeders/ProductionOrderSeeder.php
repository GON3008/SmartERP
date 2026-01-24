<?php

namespace Database\Seeders;

use App\Models\ProductionOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductionOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 50 production orders with various statuses
        ProductionOrder::factory(20)->pending()->create();
        ProductionOrder::factory(15)->create(['status' => 'in_progress']);
        ProductionOrder::factory(15)->completed()->create();
    }
}
