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
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_access_admin_transaction_areas(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/admin/categories')->assertRedirect('/login');
        $this->get('/admin/orders')->assertRedirect('/login');
        $this->get('/admin/payments')->assertRedirect('/login');
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/dashboard')->assertOk();
    }

    public function test_admin_can_access_categories(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/categories')->assertOk();
    }

    public function test_admin_can_access_orders(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/orders')->assertOk();
    }

    public function test_admin_can_access_payments(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/payments')->assertOk();
    }

    public function test_admin_can_access_users(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/users')->assertOk();
    }

    public function test_admin_can_access_roles(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/roles')->assertOk();
    }

    public function test_admin_can_access_permissions(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/permissions')->assertOk();
    }

    public function test_admin_can_access_professionals(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/professionals')->assertOk();
    }

    public function test_admin_can_access_services(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/services')->assertOk();
    }

    public function test_admin_can_access_service_requests(): void
    {
        $this->actingAs($this->adminUser())->get('/admin/service-requests')->assertOk();
    }

    public function test_user_without_required_admin_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/orders')->assertForbidden();
        $this->actingAs($user)->get('/admin/payments')->assertForbidden();
        $this->actingAs($user)->get('/admin/categories')->assertForbidden();
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

        return $admin;
    }
}
