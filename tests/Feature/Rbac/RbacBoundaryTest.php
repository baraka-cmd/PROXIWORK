<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_access_rbac_api(): void
    {
        $this->getJson('/api/v1/rbac/me')->assertUnauthorized();
        $this->getJson('/api/v1/rbac/roles')->assertUnauthorized();
    }

    public function test_client_cannot_view_rbac_catalog(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/rbac/roles')
            ->assertForbidden();
    }

    public function test_system_roles_remain_protected_from_deletion(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $systemRole = Role::where('name', 'support')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/rbac/roles/'.$systemRole->id)
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }
}
