<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\SecurityAlert;
use App\Models\SecuritySession;
use App\Models\SensitiveIntervention;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\SecuritySessionService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use LogicException;
use Tests\TestCase;

class PhaseNineSecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_authentication_failures_are_redacted_and_create_a_deduplicated_alert(): void
    {
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '192.0.2.10']);
        $service = app(AuditLogService::class);

        for ($i = 0; $i < 10; $i++) {
            $service->recordAuthenticationFailure('private@example.test', $request);
        }

        $this->assertDatabaseCount('audit_logs', 10);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login.failed',
            'category' => 'authentication',
            'outcome' => 'failure',
            'ip_address' => '192.0.2.10',
        ]);

        $event = AuditLog::query()->where('action', 'auth.login.failed')->firstOrFail();
        $this->assertArrayNotHasKey('email', $event->metadata ?? []);
        $this->assertNotSame('private@example.test', $event->metadata['identifier_hash'] ?? null);

        $this->assertDatabaseCount('security_alerts', 1);
        $alert = SecurityAlert::query()->firstOrFail();
        $this->assertSame('auth.failed-login.burst', $alert->rule_key);
        $this->assertSame('high', $alert->severity);
    }

    public function test_repeated_authorization_denials_create_a_security_alert(): void
    {
        $request = Request::create('/admin/users/999', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.12']);
        $audit = app(AuditLogService::class);

        for ($i = 0; $i < 20; $i++) {
            $audit->recordEvent(
                'security.authorization.denied',
                null,
                null,
                ['route' => 'admin.users.show', 'method' => 'GET', 'path' => 'admin/users/999'],
                $request,
                category: 'security',
                outcome: 'denied',
                severity: 'low',
            );
        }

        $this->assertDatabaseHas('security_alerts', [
            'rule_key' => 'security.forbidden-burst',
            'severity' => 'medium',
        ]);
    }

    public function test_revoke_all_user_sessions_invalidates_legacy_sanctum_tokens(): void
    {
        $actor = User::factory()->create();
        $owner = User::factory()->create();
        $createdToken = $owner->createToken('legacy-device');
        $token = $createdToken->accessToken;

        SecuritySession::query()->create([
            'user_id' => $owner->id,
            'guard' => 'sanctum',
            'identifier_hash' => hash_hmac('sha256', 'sanctum:'.$token->id, (string) config('app.key')),
            'token_id' => $token->id,
            'device_name' => 'legacy-device',
            'last_seen_at' => now(),
        ]);

        app(SecuritySessionService::class)->revokeAllForUser($owner, $actor, 'Révocation après changement de sécurité.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertNotNull(SecuritySession::query()->firstOrFail()->revoked_at);
    }

    public function test_administrative_actions_are_projected_to_interventions_with_safe_changes(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $target = User::factory()->create();
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '192.0.2.11']);

        app(AuditLogService::class)->record(
            'admin.user.suspended',
            $target,
            $actor,
            [
                'previous_status' => 'active',
                'new_status' => 'suspended',
                'password' => 'must-not-be-stored',
                'reason' => 'Signalement vérifié par le support.',
            ],
            $request,
        );

        $intervention = SensitiveIntervention::query()->firstOrFail();
        $this->assertSame($actor->id, $intervention->actor_id);
        $this->assertSame('user', $intervention->domain);
        $this->assertSame('admin.user.suspended', $intervention->action);
        $this->assertSame('active', $intervention->changes['before']['status']);
        $this->assertSame('suspended', $intervention->changes['after']['status']);
        $this->assertNotNull($intervention->reason_hash);
        $this->assertDatabaseMissing('audit_logs', ['metadata->password' => 'must-not-be-stored']);
    }

    public function test_audit_events_cannot_be_updated_or_deleted_through_eloquent(): void
    {
        $event = AuditLog::query()->create(['action' => 'immutable.test']);

        try {
            $event->update(['action' => 'tampered']);
            $this->fail('Audit log update should be rejected.');
        } catch (LogicException) {
            $this->assertSame('immutable.test', $event->fresh()->action);
        }

        try {
            $event->delete();
            $this->fail('Audit log deletion should be rejected.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $event->id]);
        }
    }

    public function test_revoking_a_sanctum_session_deletes_the_real_personal_access_token(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $owner = User::factory()->create();
        $createdToken = $owner->createToken('android-device');
        $token = $createdToken->accessToken;

        $session = SecuritySession::query()->create([
            'user_id' => $owner->id,
            'guard' => 'sanctum',
            'identifier_hash' => hash_hmac('sha256', 'sanctum:'.$token->id, (string) config('app.key')),
            'token_id' => $token->id,
            'device_name' => 'android-device',
            'last_seen_at' => now(),
        ]);

        app(SecuritySessionService::class)->revoke($session, $actor, 'Appareil déclaré perdu.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertSame($actor->id, $session->fresh()->revoked_by);
    }

    public function test_revoking_a_web_session_uses_the_configured_laravel_handler(): void
    {
        config(['session.driver' => 'array']);
        app('session')->forgetDrivers();

        $actor = User::factory()->create();
        $owner = User::factory()->create();
        $sessionId = 'web-session-to-revoke';
        $handler = app('session')->driver()->getHandler();
        $handler->write($sessionId, 'serialized-session-payload');

        $session = SecuritySession::query()->create([
            'user_id' => $owner->id,
            'guard' => 'web',
            'identifier_hash' => hash_hmac('sha256', 'web:'.$sessionId, (string) config('app.key')),
            'identifier_ciphertext' => $sessionId,
            'device_name' => 'Test browser',
            'last_seen_at' => now(),
        ]);

        app(SecuritySessionService::class)->revoke($session, $actor, 'Navigateur déclaré compromis.');

        $this->assertSame('', $handler->read($sessionId));
        $this->assertNotNull($session->fresh()->revoked_at);
    }

    public function test_security_audit_permission_cannot_read_administrative_audit_events(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('security.events.view');

        $securityEvent = AuditLog::query()->create([
            'action' => 'security.authorization.denied',
            'category' => 'security',
            'outcome' => 'denied',
        ]);
        $adminEvent = AuditLog::query()->create([
            'action' => 'admin.user.suspended',
            'category' => 'administrative',
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/audit/logs')
            ->assertOk()
            ->assertJsonFragment(['action' => 'security.authorization.denied'])
            ->assertJsonMissing(['action' => 'admin.user.suspended']);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/audit/logs/'.$adminEvent->id)
            ->assertForbidden();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/audit/logs/'.$securityEvent->id)
            ->assertOk();
    }

    public function test_client_cannot_open_admin_security_pages(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get('/admin/security/events')
            ->assertForbidden();
    }
}
