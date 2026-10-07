<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_rbac_permission_cannot_view_permissions(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::where('name', 'client')->firstOrFail());

        $this->actingAs($user)
            ->get(route('admin.permissions.index'))
            ->assertForbidden();
    }

    public function test_authorized_admin_can_view_and_filter_permissions(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('name', 'admin')->firstOrFail());

        $this->actingAs($admin)
            ->get(route('admin.permissions.index', ['group' => 'admin.professionals']))
            ->assertOk()
            ->assertSee('admin.professionals.view');

        $permission = Permission::where('name', 'admin.professionals.view')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.permissions.show', $permission))
            ->assertOk()
            ->assertSee($permission->display_name);
    }
}
