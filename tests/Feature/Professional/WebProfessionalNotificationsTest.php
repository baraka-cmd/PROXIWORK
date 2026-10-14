<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebProfessionalNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_view_only_own_notifications(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $other = User::factory()->create();
        $other->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $other->id]);

        $own = $professional->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => ['title' => 'Votre notification', 'message' => 'Message personnel', 'action' => 'order'],
        ]);

        $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => ['title' => 'Autre notification', 'message' => 'Ne doit pas apparaître', 'action' => 'order'],
        ]);

        $this->actingAs($professional)
            ->get(route('professional.notifications'))
            ->assertOk()
            ->assertSee('Votre notification')
            ->assertDontSee('Autre notification');

        $this->assertNull($own->fresh()->read_at);
    }

    public function test_unread_filter_and_mark_as_read_work(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $unread = $professional->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => ['title' => 'Non lue', 'message' => 'À traiter', 'action' => 'message'],
        ]);

        $read = $professional->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => ['title' => 'Déjà lue', 'message' => 'Terminée', 'action' => 'review'],
            'read_at' => now(),
        ]);

        $this->actingAs($professional)
            ->get(route('professional.notifications', ['status' => 'unread']))
            ->assertOk()
            ->assertSee('Non lue')
            ->assertDontSee('Déjà lue');

        $this->actingAs($professional)
            ->post(route('professional.notifications.read', $unread->id))
            ->assertRedirect();

        $this->assertNotNull($unread->fresh()->read_at);
        $this->assertNotNull($read->fresh()->read_at);
    }

    public function test_professional_can_mark_all_own_notifications_as_read(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        foreach (['one', 'two'] as $title) {
            $professional->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'account_activity',
                'data' => ['title' => $title, 'message' => 'Notification', 'action' => 'order'],
            ]);
        }

        $this->actingAs($professional)
            ->post(route('professional.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $professional->fresh()->unreadNotifications()->count());
    }

    public function test_client_cannot_access_professional_notifications(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get(route('professional.notifications'))
            ->assertForbidden();
    }

    public function test_other_user_cannot_mark_professional_notification_as_read(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $other = User::factory()->create();
        $other->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $other->id]);

        $notification = $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => ['title' => 'Privée', 'message' => 'Ne pas modifier', 'action' => 'message'],
        ]);

        $this->actingAs($professional)
            ->post(route('professional.notifications.read', $notification->id))
            ->assertNotFound();

        $this->actingAs($professional)
            ->post(route('professional.notifications.archive', $notification->id))
            ->assertNotFound();

        $this->actingAs($professional)
            ->post(route('professional.notifications.restore', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
        $this->assertNull($notification->fresh()->archived_at);
    }

    public function test_professional_can_search_filter_and_archive_only_own_notifications(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $own = $professional->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => ['title' => 'Nouvelle demande de plomberie', 'message' => 'Une demande vous attend.', 'action' => 'service_request'],
        ]);
        $archived = $professional->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => ['title' => 'Ancienne demande', 'message' => 'Historique conservé.', 'action' => 'service_request'],
            'archived_at' => now(),
        ]);

        $this->actingAs($professional)
            ->get(route('professional.notifications', ['q' => 'plomberie', 'category' => 'service_request', 'status' => 'unread']))
            ->assertOk()
            ->assertSee('Nouvelle demande de plomberie')
            ->assertDontSee('Ancienne demande');

        $this->actingAs($professional)
            ->post(route('professional.notifications.archive', $own->id))
            ->assertRedirect();

        $this->assertNotNull($own->fresh()->archived_at);
        $this->assertNotNull($own->fresh()->read_at);

        $this->actingAs($professional)
            ->get(route('professional.notifications', ['archived' => '1']))
            ->assertOk()
            ->assertSee('Nouvelle demande de plomberie');

        $this->actingAs($professional)
            ->post(route('professional.notifications.restore', $own->id))
            ->assertRedirect();

        $this->assertNull($own->fresh()->archived_at);
    }

    public function test_professional_notification_links_reject_external_hosts(): void
    {
        config(['app.url' => 'https://proxiwork.example']);

        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $professional->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => [
                'title' => 'Lien externe non autorisé',
                'message' => 'Cette URL ne doit pas être rendue comme un lien.',
                'action' => 'order',
                'url' => 'https://evil.example/phishing',
            ],
        ]);

        $professional->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'account_activity',
            'data' => [
                'title' => 'Lien malformé',
                'message' => 'Une URL malformée ne doit pas casser la page.',
                'action' => 'order',
                'url' => 'http://[',
            ],
        ]);

        $this->actingAs($professional)
            ->get(route('professional.notifications'))
            ->assertOk()
            ->assertSee('Lien externe non autorisé')
            ->assertSee('Lien malformé')
            ->assertDontSee('href="https://evil.example/phishing"', false)
            ->assertDontSee('href="http://["', false);
    }

    public function test_professional_can_update_external_notification_preferences_without_disabling_database_history(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $this->actingAs($professional)
            ->patch(route('professional.notifications.preferences.update'), [
                'email_enabled' => false,
                'sms_enabled' => true,
                'push_enabled' => false,
                'database_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $professional->id,
            'database_enabled' => true,
            'email_enabled' => false,
            'sms_enabled' => true,
            'push_enabled' => false,
        ]);
    }
}
