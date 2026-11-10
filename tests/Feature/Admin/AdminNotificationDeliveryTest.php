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
