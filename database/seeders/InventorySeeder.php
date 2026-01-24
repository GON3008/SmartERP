<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\WareHouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $warehouses = Warehouse::all();

        // Create inventory records for each product in each warehouse
        foreach ($products as $product) {
            foreach ($warehouses as $warehouse) {
                Inventory::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => rand(0, 1000),
                ]);
            }
        }

        // Create some low stock items
        Inventory::factory(20)->lowStock()->create();

        // Create some out of stock items
        Inventory::factory(10)->outOfStock()->create();
    }
}
