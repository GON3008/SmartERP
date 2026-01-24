<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $tables = ['products', 'orders', 'customers', 'employees', 'inventories', 'production_orders'];
        $actions = ['created', 'updated', 'deleted', 'viewed'];

        // Create 200 activity logs
        for ($i = 0; $i < 200; $i++) {
            $user = $users->random();
            $table = fake()->randomElement($tables);
            $action = fake()->randomElement($actions);

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'table_name' => $table,
                'record_id' => rand(1, 100),
                'description' => ucfirst($action) . ' ' . str_replace('_', ' ', $table) . ' record',
                'ip_address' => fake()->ipv4(),
                'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
            ]);
        }
    }
}
