<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => true,
        ]);
        $superAdmin->roles()->attach(Role::where('name', 'Super Admin')->first());

        // Create Admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => true,
        ]);
        $admin->roles()->attach(Role::where('name', 'Admin')->first());

        // Create Manager
        $manager = User::create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => true,
        ]);
        $manager->roles()->attach(Role::where('name', 'Manager')->first());

        // Create Sales User
        $sales = User::create([
            'name' => 'Sales User',
            'email' => 'sales@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => true,
        ]);
        $sales->roles()->attach(Role::where('name', 'Sales')->first());

        // Create Warehouse User
        $warehouse = User::create([
            'name' => 'Warehouse User',
            'email' => 'warehouse@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => true,
        ]);
        $warehouse->roles()->attach(Role::where('name', 'Warehouse Staff')->first());

        // Create HR User
        $hr = User::create([
            'name' => 'HR User',
            'email' => 'hr@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => true,
        ]);
        $hr->roles()->attach(Role::where('name', 'HR Staff')->first());

        // Create 20 random users with random roles
        User::factory(20)->create()->each(function ($user) {
            $randomRole = Role::whereNotIn('name', ['Super Admin'])->inRandomOrder()->first();
            $user->roles()->attach($randomRole);
        });
    }
}
