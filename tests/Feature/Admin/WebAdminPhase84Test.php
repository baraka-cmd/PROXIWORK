<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAdminPhase84Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_guest_cannot_access_admin_transaction_areas(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/admin/categories')->assertRedirect('/login');
        $this->get('/admin/orders')->assertRedirect('/login');
        $this->get('/admin/payments')->assertRedirect('/login');
    }

    public function test_admin_can_access_all_phase_84_read_only_areas(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('name', 'admin')->firstOrFail());

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->get('/admin/categories')->assertOk();
        $this->actingAs($admin)->get('/admin/orders')->assertOk();
        $this->actingAs($admin)->get('/admin/payments')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/admin/permissions')->assertOk();
        $this->actingAs($admin)->get('/admin/professionals')->assertOk();
        $this->actingAs($admin)->get('/admin/services')->assertOk();
        $this->actingAs($admin)->get('/admin/service-requests')->assertOk();
    }

    public function test_user_without_required_admin_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/orders')->assertForbidden();
        $this->actingAs($user)->get('/admin/payments')->assertForbidden();
        $this->actingAs($user)->get('/admin/categories')->assertForbidden();
    }
}
