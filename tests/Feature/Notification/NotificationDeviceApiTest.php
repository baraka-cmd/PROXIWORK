<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\NotificationDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_and_revoke_only_their_own_device_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notification-devices', [
                'token' => 'fcm-test-device-token-abcdefghijklmnop',
                'platform' => 'android',
            ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseHas('notification_devices', [
            'user_id' => $user->id,
            'token' => 'fcm-test-device-token-abcdefghijklmnop',
        ]);

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')
            ->deleteJson('/api/v1/notification-devices', [
                'token' => 'fcm-test-device-token-abcdefghijklmnop',
            ])
            ->assertOk();

        $this->assertDatabaseHas('notification_devices', [
            'user_id' => $user->id,
            'token' => 'fcm-test-device-token-abcdefghijklmnop',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/notification-devices', [
                'token' => 'fcm-test-device-token-abcdefghijklmnop',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('notification_devices', [
            'token' => 'fcm-test-device-token-abcdefghijklmnop',
        ]);
    }

    public function test_device_token_cannot_be_reassigned_to_another_account_without_revocation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = 'fcm-test-device-token-abcdefghijklmnop';

        NotificationDevice::query()->create([
            'user_id' => $owner->id,
            'token' => $token,
            'platform' => 'android',
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/notification-devices', [
                'token' => $token,
                'platform' => 'android',
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('notification_devices', [
            'user_id' => $owner->id,
            'token' => $token,
        ]);
    }
}
