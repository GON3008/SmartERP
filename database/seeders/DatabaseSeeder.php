<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            // 1. Auth & RBAC
            RoleSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,

            // 2. HR Module
            DepartmentSeeder::class,
            PositionSeeder::class,
            EmployeeSeeder::class,
            AttendanceSeeder::class,
            SalarySeeder::class,

            // 3. Sales & Inventory
            CustomerSeeder::class,
            ProductSeeder::class,
            WarehouseSeeder::class,
            InventorySeeder::class,
            OrderSeeder::class,

            // 4. Stock Management
            StockInSeeder::class,
            StockOutSeeder::class,

            // 5. Production
            BillOfMaterialsSeeder::class,
            ProductionOrderSeeder::class,
            ProductionLogSeeder::class,

            // 6. Analytics
            InventoryRecommendationSeeder::class,

            // 7. Activity Logs
            ActivityLogSeeder::class,
        ]);
    }
}
