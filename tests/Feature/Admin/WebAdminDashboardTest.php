<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }

    public function test_user_without_dashboard_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_with_dashboard_permission_can_view_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('name', 'admin')->firstOrFail());

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertViewHas('dashboard')
            ->assertSee('Utilisateurs')
            ->assertSee('Professionnels')
            ->assertSee('Volumes et commissions');
    }

    public function test_period_filters_are_passed_to_the_domain_service(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('name', 'admin')->firstOrFail());

        $response = $this->actingAs($user)->get('/admin/dashboard?from=2026-09-01&to=2026-09-30');

        $response->assertOk()
            ->assertSee('2026-09-01')
            ->assertSee('2026-09-30');
    }
}
