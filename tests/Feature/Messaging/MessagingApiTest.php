<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_client_can_create_one_conversation_with_a_professional(): void
    {
        [$client, $professional] = $this->participants();

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations');

        $response->assertCreated()
            ->assertJsonPath('data.type', 'client_professional')
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.professional.id', $professional->id);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_professional_can_send_and_client_can_read_and_track_unread_messages(): void
    {
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $professionalUser = User::query()->findOrFail($professional->user_id);

        $message = $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', [
                'body' => 'Bonjour, comment puis-je vous aider ?',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame($professionalUser->id, $message['sender']['id']);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 1);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/read')
            ->assertOk();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_unrelated_user_cannot_access_or_send_in_conversation(): void
    {
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $other = User::factory()->create();
        $other->assignRole('client');

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/v1/conversations/'.$conversation)
            ->assertForbidden();

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', [
                'body' => 'Message intrusif.',
            ])
            ->assertForbidden();
    }

    public function test_only_client_can_start_conversation_with_professional(): void
    {
        [, $professional] = $this->participants();
        $professionalUser = User::query()->findOrFail($professional->user_id);

        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertForbidden();
    }

    public function test_duplicate_message_is_rejected(): void
    {
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $payload = ['body' => 'Même message.'];

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', $payload)
            ->assertCreated();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_message_body_has_a_server_side_limit(): void
    {
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', [
                'body' => str_repeat('x', 5001),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_history_is_cursor_paginated(): void
    {
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($client, 'sanctum')
                ->postJson('/api/v1/conversations/'.$conversation.'/messages', [
                    'body' => 'Message '.$i,
                ])
                ->assertCreated();
        }

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/conversations/'.$conversation.'/messages?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta' => ['per_page', 'next_cursor', 'previous_cursor']]);
    }

    private function participants(): array
    {
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');

        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);

        $client = User::factory()->create();
        $client->assignRole('client');

        return [$client, $professional];
    }
}
