<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\User;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_create_and_read_own_ticket(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/support/tickets', [
                'subject' => 'Paiement',
                'category' => 'PAYMENT',
                'priority' => 'high',
                'body' => 'Besoin d’assistance',
            ])
            ->assertCreated();

        $id = $response->json('data.id');

        $this->getJson("/api/v1/support/tickets/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    public function test_claim_context_must_belong_to_the_authenticated_user(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $service = Service::factory()->create(['professional_profile_id' => $profile->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/support/tickets', [
                'subject' => 'Problème sur un service',
                'category' => 'SERVICE',
                'body' => 'Je souhaite signaler un problème sur ce service.',
                'context_type' => 'service',
                'context_id' => $service->id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_professional_can_link_claim_to_own_service(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $service = Service::factory()->create(['professional_profile_id' => $profile->id]);

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/support/tickets', [
                'subject' => 'Assistance sur mon service',
                'category' => 'SERVICE',
                'body' => 'Je rencontre un problème avec la gestion de mon service.',
                'context_type' => 'service',
                'context_id' => $service->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('support_tickets', [
            'id' => $response->json('data.id'),
            'context_type' => 'service',
            'context_id' => $service->id,
        ]);
    }

    public function test_invalid_ticket_category_is_rejected(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/support/tickets', [
                'subject' => 'Test',
                'category' => 'NOT_A_CATEGORY',
                'body' => 'Message',
            ])
            ->assertUnprocessable();
    }

    public function test_client_cannot_read_another_client_ticket(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('client');
        $other = User::factory()->create();
        $other->assignRole('client');

        $ticket = $owner->supportTickets()->create([
            'subject' => 'Test',
            'category' => 'ACCOUNT',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/support/tickets/{$ticket->id}")
            ->assertForbidden();
    }

    public function test_open_ticket_cannot_be_closed_directly(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('client');
        $support = User::factory()->create();
        $support->assignRole('support');

        $ticket = $owner->supportTickets()->create([
            'subject' => 'Test',
            'category' => 'ACCOUNT',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($support, 'sanctum')
            ->postJson("/api/v1/support/tickets/{$ticket->id}/transition", [
                'status' => 'closed',
            ])
            ->assertUnprocessable();
    }

    public function test_support_can_transition_ticket_with_valid_state_machine(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('client');
        $support = User::factory()->create();
        $support->assignRole('support');

        $ticket = $owner->supportTickets()->create([
            'subject' => 'Test',
            'category' => 'ACCOUNT',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($support, 'sanctum')
            ->postJson("/api/v1/support/tickets/{$ticket->id}/transition", [
                'status' => 'in_progress',
            ])
            ->assertOk();

        $this->actingAs($support, 'sanctum')
            ->postJson("/api/v1/support/tickets/{$ticket->id}/transition", [
                'status' => 'resolved',
            ])
            ->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'resolved',
        ]);
    }
}
