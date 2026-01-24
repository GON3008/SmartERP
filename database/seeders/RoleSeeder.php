<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions'
            ],
            [
                'name' => 'Admin',
                'description' => 'Administrative access to most features'
            ],
            [
                'name' => 'Manager',
                'description' => 'Department or team management access'
            ],
            [
                'name' => 'Sales',
                'description' => 'Access to sales and customer management'
            ],
            [
                'name' => 'Warehouse Staff',
                'description' => 'Access to inventory and stock management'
            ],
            [
                'name' => 'Production Staff',
                'description' => 'Access to production orders and manufacturing'
            ],
            [
                'name' => 'HR Staff',
                'description' => 'Access to employee and payroll management'
            ],
            [
                'name' => 'Accountant',
                'description' => 'Access to financial and accounting features'
            ],
            [
                'name' => 'Viewer',
                'description' => 'Read-only access to reports and dashboards'
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
