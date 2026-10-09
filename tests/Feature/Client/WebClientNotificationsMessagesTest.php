<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\User;
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

    public function test_professional_cannot_access_client_notifications_or_messages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $this->actingAs($user)->get('/client/notifications')->assertForbidden();
        $this->actingAs($user)->get('/client/messages')->assertForbidden();
    }
}
