<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function supportAgent(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::query()->where('name', 'support')->firstOrFail());

        return $user;
    }

    private function ticket(User $owner): SupportTicket
    {
        return SupportTicket::query()->create([
            'user_id' => $owner->id,
            'subject' => 'Problème de paiement',
            'category' => SupportTicketCategory::PAYMENT,
            'priority' => SupportTicketPriority::NORMAL,
            'status' => SupportTicketStatus::OPEN,
        ]);
    }

    public function test_support_agent_can_open_ticket_center_and_detail(): void
    {
        $agent = $this->supportAgent();
        $ticket = $this->ticket(User::factory()->create());

        $this->actingAs($agent)
            ->get(route('admin.support.index'))
            ->assertOk();

        $this->actingAs($agent)
            ->get(route('admin.support.show', $ticket))
            ->assertOk();
    }

    public function test_support_reply_uses_authenticated_agent_as_sender_and_updates_ticket(): void
    {
        $agent = $this->supportAgent();
        $ticket = $this->ticket(User::factory()->create());

        $this->actingAs($agent)
            ->post(route('admin.support.message', $ticket), [
                'body' => 'Nous avons pris votre demande en charge.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'sender_id' => $agent->id,
            'body' => 'Nous avons pris votre demande en charge.',
        ]);

        $this->assertSame(SupportTicketStatus::IN_PROGRESS, $ticket->refresh()->status);
        $this->assertNotNull($ticket->last_message_at);
    }

    public function test_closed_ticket_cannot_receive_reply_or_assignment(): void
    {
        $agent = $this->supportAgent();
        $otherAgent = User::factory()->create();
        $otherAgent->assignRole(Role::query()->where('name', 'support')->firstOrFail());
        $ticket = $this->ticket(User::factory()->create());
        $ticket->update(['status' => SupportTicketStatus::CLOSED]);

        $this->actingAs($agent)
            ->post(route('admin.support.message', $ticket), ['body' => 'Impossible'])
            ->assertSessionHasErrors('body');

        $this->actingAs($agent)
            ->post(route('admin.support.assign', $ticket), ['assigned_to' => $otherAgent->id])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, TicketMessage::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_ticket_must_be_resolved_before_close(): void
    {
        $agent = $this->supportAgent();
        $ticket = $this->ticket(User::factory()->create());

        $this->actingAs($agent)
            ->post(route('admin.support.close', $ticket))
            ->assertSessionHasErrors('status');

        $this->assertSame(SupportTicketStatus::OPEN, $ticket->refresh()->status);
    }

    public function test_non_support_user_cannot_access_admin_support(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.support.index'))
            ->assertForbidden();
    }
}
