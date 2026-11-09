<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\User;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_client_without_admin_permission_is_forbidden(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_list_search_filter_and_paginate_users(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Alice Pro', 'account_status' => UserAccountStatus::SUSPENDED]);
        $target->assignRole('client');
        User::factory()->create(['name' => 'Bob Other']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/users?search=Alice&account_status=suspended&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('data.0.account_status', 'suspended')
            ->assertJsonPath('meta.scope', 'admin.users')
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_admin_can_view_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.roles.0', 'client');
    }

    public function test_admin_can_suspend_and_reactivate_user_and_tokens_are_revoked(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->createToken('device');
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'admin-user-test-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PROXIWORK test',
            'payload' => '{}',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$user->id}/suspend", [
                'reason_code' => 'administrative_decision',
                'duration' => 'until_review',
            ])
            ->assertOk()
            ->assertJsonPath('data.account_status', 'suspended');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status' => 'suspended',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-user-test-'.$user->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$user->id}/activate", [
                'reason' => 'Les vérifications sont terminées et le compte peut être réactivé.',
            ])
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.suspended',
            'subject_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.activated',
            'subject_id' => $user->id,
        ]);
    }

    public function test_admin_cannot_suspend_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$admin->id}/suspend")
            ->assertForbidden();
    }

    public function test_suspension_requires_a_reason_and_duration(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$user->id}/suspend", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_code', 'duration']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status' => 'active',
        ]);
    }

    public function test_admin_cannot_suspend_another_administrator(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$target->id}/suspend", [
                'reason_code' => 'administrative_decision',
                'duration' => 'until_review',
            ])
            ->assertForbidden();
    }

    public function test_expired_temporary_suspensions_are_reactivated_and_audited(): void
    {
        $user = User::factory()->suspended()->create();
        $user->forceFill([
            'suspended_at' => now()->subDays(7),
            'suspended_until' => now()->subMinute(),
            'suspension_reason' => 'abusive_use',
        ])->save();

        $this->artisan('users:expire-suspensions')->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status' => 'active',
            'suspended_until' => null,
            'suspension_reason' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.suspension_expired',
            'subject_id' => $user->id,
        ]);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->suspended()->create(['email' => 'suspended@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test',
        ])->assertForbidden();
    }

    public function test_suspended_token_cannot_use_protected_application_routes(): void
    {
        $user = User::factory()->suspended()->create();
        $token = $user->createToken('suspended')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/profile')
            ->assertForbidden();
    }
}
