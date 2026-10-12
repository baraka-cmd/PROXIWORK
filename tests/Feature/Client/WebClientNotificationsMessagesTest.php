<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebClientNotificationsMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_access_notifications_or_messages(): void
    {
        $this->get('/client/notifications')->assertRedirect('/login');
        $this->get('/client/messages')->assertRedirect('/login');
    }

    public function test_client_can_access_notifications_and_messages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->get('/client/notifications')
            ->assertOk()
            ->assertViewIs('client.notifications.index');

        $this->actingAs($user)->get('/client/messages')
            ->assertOk()
            ->assertViewIs('client.messages.index');
    }


    public function test_client_can_filter_and_search_own_notifications(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $otherClient = User::factory()->create();
        $otherClient->assignRole('client');

        $client->notify(new AccountActivityNotification(
            'Nouveau devis disponible',
            'Votre professionnel a envoyé un devis.',
            'quotation',
        ));
        $client->notify(new AccountActivityNotification(
            'Paiement confirmé',
            'Votre paiement est confirmé.',
            'payment',
        ));
        $otherClient->notify(new AccountActivityNotification(
            'Notification privée',
            'Ce contenu appartient à un autre client.',
            'quotation',
        ));

        $this->actingAs($client)
            ->get('/client/notifications?category=quotation&status=unread&q=devis')
            ->assertOk()
            ->assertSee('Nouveau devis disponible')
            ->assertDontSee('Paiement confirmé')
            ->assertDontSee('Notification privée')
            ->assertViewHas('unreadCount', 2);
    }

    public function test_client_can_archive_and_restore_only_their_own_notifications(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $otherClient = User::factory()->create();
        $otherClient->assignRole('client');

        $client->notify(new AccountActivityNotification('À archiver', 'Historique à conserver.', 'quotation'));
        $otherClient->notify(new AccountActivityNotification('Privée', 'Autre propriétaire.', 'quotation'));

        $notification = $client->notifications()->firstOrFail();
        $otherNotification = $otherClient->notifications()->firstOrFail();

        $this->actingAs($client)
            ->post(route('client.notifications.archive', $otherNotification->id))
            ->assertNotFound();

        $this->actingAs($client)
            ->post(route('client.notifications.archive', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->archived_at);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($client)
            ->post(route('client.notifications.restore', $notification->id))
            ->assertRedirect();

        $this->assertNull($notification->fresh()->archived_at);
    }

    public function test_client_can_update_external_notification_preferences_but_not_disable_in_app_history(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->patch(route('client.notifications.preferences.update'), [
                'database_enabled' => false,
                'email_enabled' => false,
                'sms_enabled' => true,
                'push_enabled' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $client->id,
            'database_enabled' => true,
            'email_enabled' => false,
            'sms_enabled' => true,
            'push_enabled' => true,
        ]);
    }
\n    public function test_professional_cannot_access_client_notifications_or_messages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $this->actingAs($user)->get('/client/notifications')->assertForbidden();
        $this->actingAs($user)->get('/client/messages')->assertForbidden();
    }
}
