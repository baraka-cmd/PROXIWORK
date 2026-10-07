<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Dashboard;

use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_non_admin_without_dashboard_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_read_dashboard_and_period_is_applied(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->create(['created_at' => '2026-10-01 10:00:00']);
        User::factory()->create(['created_at' => '2026-09-01 10:00:00']);
        ProfessionalProfile::factory()->create(['created_at' => '2026-10-03 10:00:00']);
        Service::factory()->create(['created_at' => '2026-10-04 10:00:00']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard?from=2026-10-01T00:00:00Z&to=2026-10-31T23:59:59Z')
            ->assertOk()
            ->assertJsonPath('data.period.from', '2026-10-01T00:00:00+00:00')
            ->assertJsonPath('data.period.to', '2026-10-31T23:59:59+00:00')
            ->assertJsonPath('data.users.total', 3)
            ->assertJsonPath('data.users.new', 2)
            ->assertJsonPath('data.professionals.total', 1)
            ->assertJsonPath('data.professionals.new', 1)
            ->assertJsonPath('data.services.draft', 1)
            ->assertJsonPath('meta.scope', 'admin');
    }

    public function test_invalid_period_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard?from=2026-10-31&to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }
}
