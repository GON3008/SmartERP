<?php

namespace Database\Seeders;

use App\Models\WareHouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            ['name' => 'Main Warehouse Hanoi', 'location' => 'Long Bien, Hanoi'],
            ['name' => 'Warehouse Hanoi 2', 'location' => 'Gia Lam, Hanoi'],
            ['name' => 'Main Warehouse HCMC', 'location' => 'District 7, Ho Chi Minh City'],
            ['name' => 'Warehouse Thu Duc', 'location' => 'Thu Duc City, Ho Chi Minh'],
            ['name' => 'Warehouse Da Nang', 'location' => 'Hoa Khanh, Da Nang'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::create($warehouse);
        }
    }
}
