<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditRetentionAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_retention_command_removes_only_expired_logs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $old = AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'old',
        ]);

        $old->forceFill([
            'created_at' => now()->subDays(181),
            'updated_at' => now()->subDays(181),
        ])->saveQuietly();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'recent',
        ]);

        $this->artisan('audit:prune --days=180')->assertExitCode(0);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'old']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'recent']);
    }

    public function test_client_cannot_read_a_specific_audit_log(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $log = AuditLog::create([
            'user_id' => $client->id,
            'action' => 'protected_action',
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/audit/logs/'.$log->id)
            ->assertForbidden();
    }
}
