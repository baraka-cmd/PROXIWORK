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

    public function test_guest_and_client_cannot_view_rbac_catalog(): void
    {
        $this->getJson('/api/v1/rbac/roles')->assertUnauthorized();

        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/rbac/roles')
            ->assertForbidden();
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
            ->assertOk();

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

    public function test_role_manager_cannot_grant_a_permission_they_do_not_have(): void
    {
        $roleManager = Role::create([
            'name' => 'limited_role_manager',
            'display_name' => 'Gestionnaire RBAC limité',
            'description' => 'Can manage only permissions already assigned to this role.',
            'is_system' => false,
        ]);
        $roleManager->permissions()->attach(Permission::where('name', 'rbac.manage')->value('id'));

        $user = User::factory()->create();
        $user->assignRole($roleManager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/rbac/roles', [
                'name' => 'elevated_role',
                'display_name' => 'Rôle privilégié',
                'permission_ids' => [
                    Permission::where('name', 'rbac.manage')->value('id'),
                    Permission::where('name', 'admin.users.suspend')->value('id'),
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('permission_ids');

        $this->assertDatabaseMissing('roles', ['name' => 'elevated_role']);
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

    public function test_admin_can_assign_and_revoke_roles_and_existing_sessions_are_revoked(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $target = User::factory()->create();
        $target->assignRole('client');
        $target->createToken('old-device');

        $professionalRoleId = Role::where('name', 'professional')->value('id');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/rbac/users/'.$target->id.'/roles', [
                'role_ids' => [$professionalRoleId],
            ])
            ->assertOk()
            ->assertJsonPath('data.roles.0.name', 'professional');

        $target->refresh();
        $this->assertTrue($target->hasRole('professional'));
        $this->assertFalse($target->hasRole('client'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('professional_profiles', [
            'user_id' => $target->id,
            'status' => 'draft',
            'visibility' => 'private',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $target->id,
            'action' => 'admin.user.roles_updated',
        ]);
    }

    public function test_role_manager_cannot_assign_a_role_with_permissions_they_do_not_have(): void
    {
        $limitedRoleManager = Role::create([
            'name' => 'limited_user_role_manager',
            'display_name' => 'Gestionnaire limité des rôles utilisateurs',
            'description' => 'May only assign permissions already granted to them.',
            'is_system' => false,
        ]);
        $limitedRoleManager->permissions()->attach(Permission::where('name', 'rbac.manage')->value('id'));

        $actor = User::factory()->create();
        $actor->assignRole($limitedRoleManager);

        $target = User::factory()->create();
        $target->assignRole('client');

        $this->actingAs($actor, 'sanctum')
            ->putJson('/api/v1/rbac/users/'.$target->id.'/roles', [
                'role_ids' => [Role::where('name', 'professional')->value('id')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');

        $this->assertTrue($target->fresh()->hasRole('client'));
        $this->assertFalse($target->fresh()->hasRole('professional'));
    }

    public function test_last_administrator_cannot_be_stripped_of_the_admin_role(): void
    {
        $limitedRoleManager = Role::create([
            'name' => 'last_admin_guard',
            'display_name' => 'Gestionnaire RBAC',
            'description' => 'Can manage roles within granted permissions.',
            'is_system' => false,
        ]);
        $limitedRoleManager->permissions()->attach(Permission::where('name', 'rbac.manage')->value('id'));

        $actor = User::factory()->create();
        $actor->assignRole($limitedRoleManager);

        $onlyAdmin = User::factory()->create();
        $onlyAdmin->assignRole('admin');

        $this->actingAs($actor, 'sanctum')
            ->putJson('/api/v1/rbac/users/'.$onlyAdmin->id.'/roles', [
                'role_ids' => [$limitedRoleManager->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');

        $this->assertTrue($onlyAdmin->fresh()->hasRole('admin'));
    }
}
