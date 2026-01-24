<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 30 raw materials
        Product::factory(30)->rawMaterial()->create();

        // Create 70 finished goods
        Product::factory(70)->finishedGood()->create();
    }
}
