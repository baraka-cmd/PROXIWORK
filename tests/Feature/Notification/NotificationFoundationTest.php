<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Jobs\Notifications\SendNotificationEmail;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Notification\TransactionalNotificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_user_can_read_and_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->notificationPreference()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'database_enabled',
                    'email_enabled',
                    'sms_enabled',
                    'push_enabled',
                ],
                'message',
                'meta',
            ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/notifications/preferences', [
                'database_enabled' => false,
                'email_enabled' => false,
                'push_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.database_enabled', true)
            ->assertJsonPath('data.email_enabled', false)
            ->assertJsonPath('data.push_enabled', true);
    }

    public function test_user_can_list_and_mark_only_their_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $other = User::factory()->create();
        $other->assignRole('client');

        $user->notify(new AccountActivityNotification('Test', 'Notification test', 'test'));
        $other->notify(new AccountActivityNotification('Other', 'Other notification', 'other'));

        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($user->notifications()->whereKey($notification->id)->first()->read_at);

        $otherNotification = $other->notifications()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$otherNotification->id}/read")
            ->assertNotFound();
    }

    public function test_mark_all_as_read_only_affects_current_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $other = User::factory()->create();
        $other->assignRole('client');

        $user->notify(new AccountActivityNotification('One', 'One', 'one'));
        $other->notify(new AccountActivityNotification('Two', 'Two', 'two'));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_guests_cannot_access_notifications_or_preferences(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/preferences')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/preferences', [
            'email_enabled' => false,
        ])->assertUnauthorized();
    }

    public function test_notification_preferences_are_isolated_between_users(): void
    {
        $first = User::factory()->create();
        $first->assignRole('client');
        $first->notificationPreference()->create();

        $second = User::factory()->create();
        $second->assignRole('client');

        $this->actingAs($first, 'sanctum')
            ->patchJson('/api/v1/notifications/preferences', [
                'email_enabled' => false,
                'push_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.email_enabled', false)
            ->assertJsonPath('data.push_enabled', true);

        $this->actingAs($second, 'sanctum')
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.email_enabled', true)
            ->assertJsonPath('data.push_enabled', false);
    }

    public function test_transactional_notification_service_covers_phase_three_events(): void
    {
        $recipient = User::factory()->create();
        $recipient->assignRole('client');

        $service = app(TransactionalNotificationService::class);

        $service->serviceRequestCreated($recipient, 'Plomberie');
        $service->quotationCreated($recipient);
        $service->quotationAccepted($recipient);
        $service->paymentSucceeded($recipient);
        $service->orderConfirmed($recipient);
        $service->messageReceived($recipient);
        $service->reviewReceived($recipient);

        $this->assertSame(7, $recipient->notifications()->count());
        $actions = $recipient->notifications()
            ->get()
            ->map(fn ($notification): string => (string) ($notification->data['action'] ?? ''))
            ->all();

        $this->assertEqualsCanonicalizing(
            [
                'service_request',
                'quotation',
                'quotation',
                'payment',
                'order',
                'message',
                'review',
            ],
            $actions,
        );
    }


    public function test_api_notification_filters_exclude_other_users_and_sanitize_external_urls(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $other = User::factory()->create();
        $other->assignRole('client');

        $user->notify(new AccountActivityNotification(
            'Nouveau devis',
            'Une proposition est disponible.',
            'quotation',
            'https://evil.example/phishing',
        ));
        $user->notify(new AccountActivityNotification(
            'Paiement confirmé',
            'Le paiement est confirmé.',
            'payment',
        ));
        $other->notify(new AccountActivityNotification(
            'Notification privée',
            'Ne doit pas être visible.',
            'quotation',
        ));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications?category=quotation&status=unread&q=devis')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.data.title', 'Nouveau devis')
            ->assertJsonPath('data.0.data.url', null);
    }

    public function test_api_can_archive_and_restore_only_owned_notifications(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $other = User::factory()->create();
        $other->assignRole('client');

        $user->notify(new AccountActivityNotification('À archiver', 'Historique conservé.', 'quotation'));
        $other->notify(new AccountActivityNotification('Privée', 'Autre propriétaire.', 'quotation'));

        $notification = $user->notifications()->firstOrFail();
        $otherNotification = $other->notifications()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$otherNotification->id}/archive")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/archive")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->archived_at);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/restore")
            ->assertOk();

        $this->assertNull($notification->fresh()->archived_at);
    }


    public function test_transactional_notifications_are_idempotent_when_an_event_key_is_reused(): void
    {
        $recipient = User::factory()->create();
        $recipient->assignRole('client');

        $service = app(TransactionalNotificationService::class);
        $service->quotationCreated($recipient, '/client/quotes/quote-42', 'quotation-created:42');
        $service->quotationCreated($recipient, '/client/quotes/quote-42', 'quotation-created:42');

        $this->assertSame(1, $recipient->notifications()->count());
        $this->assertSame(1, NotificationDelivery::query()->where('user_id', $recipient->id)->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
            'deduplication_key' => hash('sha256', $recipient->id.'|quotation|quotation-created:42'),
        ]);
    }

    public function test_pending_email_delivery_is_queued_by_the_recovery_command(): void
    {
        Queue::fake();

        $recipient = User::factory()->create();
        $recipient->assignRole('client');

        app(TransactionalNotificationService::class)->quotationCreated(
            $recipient,
            '/client/quotes/quote-43',
            'quotation-created:43',
        );

        $delivery = NotificationDelivery::query()->where('user_id', $recipient->id)->firstOrFail();

        $this->artisan('notifications:dispatch-pending')->assertSuccessful();

        Queue::assertPushed(SendNotificationEmail::class);
        $this->assertNotNull($delivery->fresh()->queued_at);
    }

    public function test_log_mailer_is_recorded_as_simulated_not_sent(): void
    {
        config(['mail.default' => 'log']);

        $recipient = User::factory()->create();
        $recipient->assignRole('client');

        app(TransactionalNotificationService::class)->quotationCreated(
            $recipient,
            null,
            'quotation-created:44',
        );

        $delivery = NotificationDelivery::query()->where('user_id', $recipient->id)->firstOrFail();

        (new SendNotificationEmail((int) $delivery->id))->handle();

        $this->assertSame('SIMULATED', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->sent_at);
    }


    public function test_local_sms_simulator_records_only_a_masked_recipient_and_never_sends_a_real_sms(): void
    {
        $recipient = User::factory()->create();
        $recipient->assignRole('client');
        $recipient->profile()->create(['first_name' => 'Test', 'last_name' => 'Client', 'phone' => '+243970001234']);
        $recipient->notificationPreference()->create(['sms_enabled' => true]);

        app(TransactionalNotificationService::class)->quotationCreated(
            $recipient,
            null,
            'quotation-created:sms-simulation-55',
        );

        $delivery = NotificationDelivery::query()
            ->where('user_id', $recipient->id)
            ->where('channel', 'sms')
            ->firstOrFail();

        $this->assertSame('SIMULATED', $delivery->status);
        $this->assertSame('***1234', $delivery->recipient_hint);
        $this->assertSame('SMS_PROVIDER_NOT_CONFIGURED', $delivery->last_error_code);
        $this->assertNull($delivery->sent_at);
    }

    public function test_critical_security_alert_keeps_email_delivery_enabled_when_optional_email_is_disabled(): void
    {
        $recipient = User::factory()->create();
        $recipient->assignRole('client');
        $recipient->notificationPreference()->create(['email_enabled' => false]);

        app(TransactionalNotificationService::class)->securityAlert(
            $recipient,
            'Alerte de sécurité',
            'Un événement de sécurité important a été détecté.',
            null,
            'security-event:abc',
        );

        $this->assertSame(1, NotificationDelivery::query()->where('user_id', $recipient->id)->count());
        $this->assertSame('critical', $recipient->notifications()->firstOrFail()->data['priority']);
    }

    public function test_notification_channel_respects_preferences(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $user->notificationPreference()->create([
            'database_enabled' => false,
            'email_enabled' => false,
            'sms_enabled' => false,
            'push_enabled' => false,
        ]);

        $notification = new AccountActivityNotification('Test', 'Test', 'test');

        // In-app history is mandatory even if a legacy preference row disables it.
        $this->assertSame(['database'], $notification->via($user));

        $critical = new AccountActivityNotification('Alerte de sécurité', 'Activité sensible détectée.', 'account', null, 'critical');
        $this->assertSame(['database', 'mail'], $critical->via($user));
    }
}
