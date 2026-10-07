<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\User;
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
        $client=User::factory()->create();
        $client->assignRole('client');

        $response=$this->actingAs($client,'sanctum')
            ->postJson('/api/v1/support/tickets',[
                'subject'=>'Paiement',
                'category'=>'PAYMENT',
                'priority'=>'high',
                'body'=>'Besoin d’assistance',
            ])
            ->assertCreated();

        $id=$response->json('data.id');

        $this->getJson("/api/v1/support/tickets/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id',$id);
    }

    public function test_client_cannot_read_another_client_ticket(): void
    {
        $owner=User::factory()->create();
        $owner->assignRole('client');
        $other=User::factory()->create();
        $other->assignRole('client');
        $ticket=$owner->supportTickets()->create([
            'subject'=>'Test',
            'category'=>'ACCOUNT',
            'priority'=>'normal',
            'status'=>'open',
        ]);

        $this->actingAs($other,'sanctum')
            ->getJson("/api/v1/support/tickets/{$ticket->id}")
            ->assertForbidden();
    }

    public function test_support_can_transition_ticket_with_valid_state_machine(): void
    {
        $owner=User::factory()->create();
        $owner->assignRole('client');
        $support=User::factory()->create();
        $support->assignRole('support');
        $ticket=$owner->supportTickets()->create([
            'subject'=>'Test',
            'category'=>'ACCOUNT',
            'priority'=>'normal',
            'status'=>'open',
        ]);

        $this->actingAs($support,'sanctum')
            ->postJson("/api/v1/support/tickets/{$ticket->id}/transition",['status'=>'in_progress'])
            ->assertOk();

        $this->actingAs($support,'sanctum')
            ->postJson("/api/v1/support/tickets/{$ticket->id}/transition",['status'=>'resolved'])
            ->assertOk();

        $this->assertDatabaseHas('support_tickets',['id'=>$ticket->id,'status'=>'resolved']);
    }
}
