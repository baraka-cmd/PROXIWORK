<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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
                'email_enabled' => false,
                'push_enabled' => true,
            ])
            ->assertOk()
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
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);

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

        $this->assertSame([], $notification->via($user));
    }
}
