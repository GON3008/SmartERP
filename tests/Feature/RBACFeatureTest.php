<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('rbac')]
class RBACFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected array $protectedRoutes = [
        ['method' => 'get',  'uri' => '/api/users'],
        ['method' => 'post', 'uri' => '/api/products'],
        ['method' => 'get',  'uri' => '/api/orders'],
    ];

    #[Test]
    public function guest_cannot_access_any_protected_route(): void
    {
        foreach ($this->protectedRoutes as $route) {
            $method = $route['method'] . 'Json';

            $response = $this->$method($route['uri']);

            $response->assertStatus(401);
        }
    }
}
