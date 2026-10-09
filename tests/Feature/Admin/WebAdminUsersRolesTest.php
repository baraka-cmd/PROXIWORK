<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAdminUsersRolesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::where('name', 'admin')->firstOrFail());

        return $user;
    }

    public function test_guest_is_redirected_from_users_and_roles(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/admin/roles')->assertRedirect('/login');
    }

    public function test_user_without_permissions_is_forbidden(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
    }

    public function test_admin_can_browse_users_and_roles(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertViewIs('admin.users.index');
        $this->actingAs($admin)->get('/admin/roles')->assertOk()->assertViewIs('admin.roles.index');
    }

    public function test_user_search_and_detail_are_scoped_through_existing_service_and_policy(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Unique Platform User', 'email' => 'unique@example.test']);

        $this->actingAs($admin)
            ->get('/admin/users?search=Unique+Platform')
            ->assertOk()
            ->assertSee('Unique Platform User');

        $this->actingAs($admin)
            ->get('/admin/users/'.$target->id)
            ->assertOk()
            ->assertSee('unique@example.test');
    }

    public function test_admin_can_create_and_edit_custom_role_but_system_role_is_protected(): void
    {
        $admin = $this->admin();
        $permission = Permission::where('name', 'users.view')->firstOrFail();

        $response = $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'content_manager',
            'display_name' => 'Gestionnaire de contenu',
            'description' => 'Gestion du contenu.',
            'permission_ids' => [$permission->id],
        ]);

        $role = Role::where('name', 'content_manager')->firstOrFail();

        $response->assertRedirect('/admin/roles/'.$role->id);
        $this->assertTrue($role->fresh()->permissions()->whereKey($permission->id)->exists());

        $this->actingAs($admin)
            ->get('/admin/roles/'.$role->id.'/edit')
            ->assertOk()
            ->assertSee('Gestionnaire de contenu');

        $system = Role::where('name', 'admin')->firstOrFail();
        $this->actingAs($admin)->get('/admin/roles/'.$system->id.'/edit')->assertForbidden();
        $this->actingAs($admin)->delete('/admin/roles/'.$system->id)->assertForbidden();
    }

    public function test_user_suspend_and_activate_use_existing_domain_service(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)->post('/admin/users/'.$target->id.'/suspend')->assertRedirect();
        $this->assertSame('suspended', $target->fresh()->account_status->value);

        $this->actingAs($admin)->post('/admin/users/'.$target->id.'/activate')->assertRedirect();
        $this->assertSame('active', $target->fresh()->account_status->value);
    }
}
