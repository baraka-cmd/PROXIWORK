<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_cannot_manage_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/rbac/roles', [
                'name' => 'billing_manager',
                'display_name' => 'Billing Manager',
            ]);

        $response->assertForbidden();
    }

    public function test_moderator_can_view_but_cannot_manage_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/rbac/roles')
            ->assertOk()
            ;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/rbac/roles', [
                'name' => 'billing_manager',
                'display_name' => 'Billing Manager',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_role_and_assign_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $permissionId = Permission::where('name', 'users.view')->value('id');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/rbac/roles', [
                'name' => 'billing_manager',
                'display_name' => 'Billing Manager',
                'description' => 'Can review billing data.',
                'permission_ids' => [$permissionId],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'billing_manager');

        $this->assertDatabaseHas('roles', ['name' => 'billing_manager']);
        $this->assertDatabaseHas('permission_role', [
            'permission_id' => $permissionId,
            'role_id' => Role::where('name', 'billing_manager')->value('id'),
        ]);
    }

    public function test_system_role_cannot_be_deleted_even_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $role = Role::where('name', 'support')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/rbac/roles/'.$role->id)
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_user_can_read_its_effective_roles_and_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/rbac/me')
            ->assertOk();

        $this->assertContains('services.manage', $response->json('data.permissions'));
    }
}
