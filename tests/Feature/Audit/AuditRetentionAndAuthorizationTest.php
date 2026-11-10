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

    public function test_retention_command_prunes_expired_security_history_but_keeps_open_alerts(): void
    {
        $user = User::factory()->create();
        $cutoff = now()->subDays(181);

        $expiredSession = SecuritySession::query()->create([
            'user_id' => $user->id,
            'guard' => 'sanctum',
            'identifier_hash' => hash('sha256', 'expired-session'),
            'token_id' => 999991,
            'last_seen_at' => $cutoff,
            'expires_at' => $cutoff,
            'revoked_at' => $cutoff,
        ]);

        $activeSession = SecuritySession::query()->create([
            'user_id' => $user->id,
            'guard' => 'sanctum',
            'identifier_hash' => hash('sha256', 'active-session'),
            'token_id' => 999992,
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $oldIntervention = SensitiveIntervention::query()->create([
            'actor_id' => $user->id,
            'domain' => 'user',
            'action' => 'admin.user.suspended',
            'result' => 'succeeded',
            'created_at' => $cutoff,
        ]);

        $oldResolvedAlert = SecurityAlert::query()->create([
            'rule_key' => 'old.resolved',
            'fingerprint' => hash('sha256', 'old-resolved'),
            'severity' => 'low',
            'status' => 'resolved',
            'title' => 'Old resolved alert',
            'occurrences' => 1,
            'first_seen_at' => $cutoff,
            'last_seen_at' => $cutoff,
        ]);

        $openAlert = SecurityAlert::query()->create([
            'rule_key' => 'open.investigation',
            'fingerprint' => hash('sha256', 'open-investigation'),
            'severity' => 'high',
            'status' => 'investigating',
            'title' => 'Open investigation',
            'occurrences' => 1,
            'first_seen_at' => $cutoff,
            'last_seen_at' => $cutoff,
        ]);

        $this->artisan('audit:prune --days=180')->assertExitCode(0);

        $this->assertDatabaseMissing('security_sessions', ['id' => $expiredSession->id]);
        $this->assertDatabaseHas('security_sessions', ['id' => $activeSession->id]);
        $this->assertDatabaseMissing('sensitive_interventions', ['id' => $oldIntervention->id]);
        $this->assertDatabaseMissing('security_alerts', ['id' => $oldResolvedAlert->id]);
        $this->assertDatabaseHas('security_alerts', ['id' => $openAlert->id]);
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
