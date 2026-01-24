<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BillOfMaterialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get finished goods and raw materials
        $finishedGoods = Product::where('category', '!=', 'Raw Materials')->get();
        $rawMaterials = Product::where('category', 'Raw Materials')->get();

        // Create BOM for each finished good
        foreach ($finishedGoods as $product) {
            // Each product needs 2-5 different materials
            $numMaterials = rand(2, 5);
            $selectedMaterials = $rawMaterials->random(min($numMaterials, $rawMaterials->count()));

            foreach ($selectedMaterials as $material) {
                BillOfMaterial::create([
                    'product_id' => $product->id,
                    'material_id' => $material->id,
                    'quantity_required' => rand(1, 10),
                ]);
            }
        }
    }
}
