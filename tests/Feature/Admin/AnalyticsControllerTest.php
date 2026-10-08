<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_analytics_with_default_period(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

        $this->actingAs($admin)
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('Analytics');
    }

    public function test_analytics_rejects_periods_longer_than_one_year(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

        $this->actingAs($admin)
            ->get(route('admin.analytics', [
                'range' => 'custom',
                'from' => '2024-01-01',
                'to' => '2026-01-01',
            ]))
            ->assertSessionHasErrors('to');
    }

    public function test_non_admin_cannot_access_analytics(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.analytics'))
            ->assertForbidden();
    }
}
