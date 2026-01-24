<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create employees for existing users
        $users = User::all();

        foreach ($users as $user) {
            Employee::factory()->create([
                'user_id' => $user->id,
                'full_name' => $user->name,
                'email' => $user->email,
            ]);
        }

        // Create additional 30 employees with new users
        Employee::factory(30)->create();
    }
}
