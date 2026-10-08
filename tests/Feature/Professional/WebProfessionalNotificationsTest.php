<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
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

        $this->assertNull($notification->fresh()->read_at);
    }
}
