<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_login_and_logout_are_audited(): void
    {
        $user = User::factory()->create([
            'email' => 'audit@example.com',
            'password' => 'SecurePass1!',
        ]);
        $user->assignRole('client');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'audit@example.com',
            'password' => 'SecurePass1!',
            'device_name' => 'test-device',
        ])->assertOk();

        $login->assertJsonStructure(['data' => ['token']]);

        $token = $login->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'logout',
        ]);
    }

    public function test_audit_metadata_does_not_store_password_or_token(): void
    {
        $user = User::factory()->create([
            'email' => 'safe@example.com',
            'password' => 'SecurePass1!',
        ]);
        $user->assignRole('client');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'safe@example.com',
            'password' => 'SecurePass1!',
            'device_name' => 'secret-device',
        ])->assertOk();

        $log = AuditLog::where('action', 'login')->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->metadata ?? []);
        $this->assertArrayNotHasKey('token', $log->metadata ?? []);
        $this->assertNotSame('SecurePass1!', $log->metadata['password'] ?? null);
    }

    public function test_audit_api_is_not_available_to_client(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit/logs')
            ->assertForbidden();
    }

    public function test_admin_can_read_audit_logs_but_there_is_no_write_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'test_action',
            'metadata' => ['safe' => true],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit/logs')
            ->assertOk()
            ->assertJsonStructure(['data', 'message', 'meta']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/audit/logs')
            ->assertMethodNotAllowed();
    }
}
