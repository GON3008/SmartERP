<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Permission;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = ['create', 'read', 'update', 'delete', 'manage'];
        $resources = ['users', 'products', 'orders', 'inventory', 'employees', 'reports'];
        
        return [
            'name' => fake()->unique()->randomElement($actions) . '.' . fake()->randomElement($resources),
            'description' => fake()->sentence(),
        ];
    }
}
