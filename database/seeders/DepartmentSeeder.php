<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'Sales & Marketing', 'description' => 'Responsible for sales, marketing, and customer relations'],
            ['name' => 'Production', 'description' => 'Manufacturing and production operations'],
            ['name' => 'Warehouse & Logistics', 'description' => 'Inventory management and logistics'],
            ['name' => 'Human Resources', 'description' => 'Employee management and development'],
            ['name' => 'Finance & Accounting', 'description' => 'Financial planning and accounting'],
            ['name' => 'IT & Technology', 'description' => 'Information technology and systems'],
            ['name' => 'Quality Control', 'description' => 'Product quality assurance and testing'],
            ['name' => 'Research & Development', 'description' => 'Product research and innovation'],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}
