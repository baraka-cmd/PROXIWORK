<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_access_notifications_or_preferences(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/preferences')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/preferences', [])->assertUnauthorized();
    }

    public function test_notification_preferences_are_isolated_per_user(): void
    {
        $first = User::factory()->create();
        $first->assignRole('client');
        $second = User::factory()->create();
        $second->assignRole('client');

        $this->actingAs($first, 'sanctum')->patchJson('/api/v1/notifications/preferences', [
            'email_enabled' => false,
            'push_enabled' => true,
        ])->assertCreated();

        $this->actingAs($second, 'sanctum')
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.email_enabled', true)
            ->assertJsonPath('data.push_enabled', false);
    }

    public function test_notification_preference_validation_rejects_non_boolean_values(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/notifications/preferences', [
                'email_enabled' => 'not-a-boolean',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email_enabled']);
    }

    public function test_marking_all_notifications_read_does_not_touch_another_user(): void
    {
        $first = User::factory()->create();
        $first->assignRole('client');
        $second = User::factory()->create();
        $second->assignRole('client');

        Notification::send($first, new \App\Notifications\AccountActivityNotification('One', 'One', 'one'));
        Notification::send($second, new \App\Notifications\AccountActivityNotification('Two', 'Two', 'two'));

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $first->unreadNotifications()->count());
        $this->assertSame(1, $second->unreadNotifications()->count());
    }
}
