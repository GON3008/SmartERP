<?php

namespace Database\Seeders;

use App\Models\StockIn;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StockInSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 200 stock in records
        StockIn::factory(200)->create();
    }
}
