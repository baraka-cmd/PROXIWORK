<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_consult_delivery_history_and_details(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $recipient = User::factory()->create();
        $recipient->notify(new AccountActivityNotification('Test', 'Message de test', 'test'));
        $notification = $recipient->notifications()->firstOrFail();

        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'user_id' => $recipient->id,
            'recipient_hint' => 'b***@example.test',
            'channel' => 'mail',
            'status' => 'FAILED',
            'attempts' => 2,
            'last_error_code' => 'ProviderUnavailable',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notifications.deliveries.index'))
            ->assertOk()
            ->assertSee('Notifications et livraisons')
            ->assertSee('FAILED');

        $this->actingAs($admin)
            ->get(route('admin.notifications.deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Livraison #'.$delivery->id)
            ->assertSee('Message de test');
    }

    public function test_each_provider_attempt_is_preserved_with_its_final_result(): void
    {
        $user = User::factory()->create();
        $user->notify(new AccountActivityNotification('Tentative', 'Historique', 'test'));
        $notification = $user->notifications()->firstOrFail();
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'recipient_hint' => 'b***@example.test',
            'channel' => 'mail',
            'status' => 'PENDING',
            'attempts' => 0,
        ]);

        $delivery->update([
            'status' => 'PROCESSING',
            'attempts' => 1,
            'processing_at' => now(),
        ]);
        $delivery->update([
            'status' => 'FAILED',
            'processing_at' => null,
            'last_error_code' => 'ProviderRejected',
        ]);

        $this->assertDatabaseHas('notification_delivery_attempts', [
            'notification_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => 'FAILED',
            'error_code' => 'ProviderRejected',
        ]);

        $delivery->refresh()->forceFill(['status' => 'RETRYING'])->saveQuietly();
        $this->assertDatabaseHas('notification_delivery_attempts', [
            'notification_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => 'FAILED',
        ]);
    }

    public function test_admin_can_retry_only_classified_transient_provider_failures(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $recipient = User::factory()->create();
        $recipient->notify(new AccountActivityNotification('Tentative', 'Historique', 'test'));
        $notification = $recipient->notifications()->firstOrFail();
        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'user_id' => $recipient->id,
            'recipient_hint' => 'b***@example.test',
            'channel' => 'mail',
            'status' => 'FAILED',
            'attempts' => 1,
            'last_error_code' => 'Symfony\\\\Component\\\\Mailer\\\\Exception\\\\TransportException',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.deliveries.retry', $delivery))
            ->assertRedirect();

        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'RETRYING']);
    }

    public function test_admin_cannot_retry_a_permanent_notification_failure(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $recipient = User::factory()->create();
        $delivery = NotificationDelivery::query()->create([
            'user_id' => $recipient->id,
            'recipient_hint' => 'b***@example.test',
            'channel' => 'mail',
            'status' => 'FAILED',
            'attempts' => 1,
            'last_error_code' => 'EMAIL_DISABLED_BY_PREFERENCE',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.notifications.deliveries.retry', $delivery))
            ->assertStatus(409);
    }

    public function test_user_without_notification_permission_cannot_consult_delivery_history(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('support');

        $this->actingAs($user)
            ->get(route('admin.notifications.deliveries.index'))
            ->assertForbidden();
    }
}
