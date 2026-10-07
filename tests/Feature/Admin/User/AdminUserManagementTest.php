<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\User;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$user->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.account_status', 'suspended');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status' => 'suspended',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/users/{$user->id}/activate")
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
