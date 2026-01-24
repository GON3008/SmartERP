<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = ['users', 'roles', 'permissions', 'employees', 'departments', 'positions',
                    'attendances', 'salaries', 'customers', 'products', 'orders', 'warehouses',
                    'inventories', 'stock', 'production', 'reports'];

        $actions = ['view', 'create', 'edit', 'delete'];

        $permissions = [];

        // Create permissions for each module
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => "{$action}.{$module}",
                    'description' => ucfirst($action) . ' ' . ucfirst($module)
                ];
            }
        }

        // Additional special permissions
        $specialPermissions = [
            ['name' => 'manage.all', 'description' => 'Full system management'],
            ['name' => 'view.dashboard', 'description' => 'View dashboard'],
            ['name' => 'export.reports', 'description' => 'Export reports'],
            ['name' => 'approve.orders', 'description' => 'Approve orders'],
            ['name' => 'approve.production', 'description' => 'Approve production orders'],
        ];

        foreach (array_merge($permissions, $specialPermissions) as $permission) {
            Permission::create($permission);
        }

        // Assign all permissions to Super Admin
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->attach(Permission::all());
        }

        // Assign specific permissions to other roles
        $this->assignRolePermissions();
    }

    private function assignRolePermissions(): void
    {
        // Admin - most permissions except manage.all
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $adminPermissions = Permission::whereNotIn('name', ['manage.all', 'delete.users', 'delete.roles'])
                ->pluck('id');
            $admin->permissions()->attach($adminPermissions);
        }

        // Sales - customer, order, product view
        $sales = Role::where('name', 'Sales')->first();
        if ($sales) {
            $salesPermissions = Permission::where('name', 'like', '%.customers')
                ->orWhere('name', 'like', '%.orders')
                ->orWhere('name', 'like', 'view.products')
                ->orWhere('name', 'like', 'view.inventories')
                ->pluck('id');
            $sales->permissions()->attach($salesPermissions);
        }

        // Warehouse Staff
        $warehouse = Role::where('name', 'Warehouse Staff')->first();
        if ($warehouse) {
            $warehousePermissions = Permission::where('name', 'like', '%.warehouses')
                ->orWhere('name', 'like', '%.inventories')
                ->orWhere('name', 'like', '%.stock')
                ->orWhere('name', 'like', 'view.products')
                ->pluck('id');
            $warehouse->permissions()->attach($warehousePermissions);
        }

        // Production Staff
        $production = Role::where('name', 'Production Staff')->first();
        if ($production) {
            $productionPermissions = Permission::where('name', 'like', '%.production')
                ->orWhere('name', 'like', 'view.products')
                ->orWhere('name', 'like', 'view.inventories')
                ->pluck('id');
            $production->permissions()->attach($productionPermissions);
        }

        // HR StafA
        $hr = Role::where('name', 'HR Staff')->first();
        if ($hr) {
            $hrPermissions = Permission::where('name', 'like', '%.employees')
                ->orWhere('name', 'like', '%.departments')
                ->orWhere('name', 'like', '%.positions')
                ->orWhere('name', 'like', '%.attendances')
                ->orWhere('name', 'like', '%.salaries')
                ->pluck('id');
            $hr->permissions()->attach($hrPermissions);
        }

        // Viewer - only view permissions
        $viewer = Role::where('name', 'Viewer')->first();
        if ($viewer) {
            $viewPermissions = Permission::where('name', 'like', 'view.%')->pluck('id');
            $viewer->permissions()->attach($viewPermissions);
        }
    }
}
