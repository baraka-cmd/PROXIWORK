<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\ConversationStatus;
use App\Jobs\Notifications\SendNotificationEmail;
use App\Models\Conversation;
use App\Models\NotificationDelivery;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
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
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $client->id,
            'type' => AccountActivityNotification::class,
        ]);

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

    public function test_idempotency_key_returns_the_original_message_without_duplicate_writes(): void
    {
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $payload = [
            'body' => 'Message envoyé une seule fois.',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $first = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', $payload)
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_identical_message_text_can_be_sent_again_with_a_new_idempotency_key(): void
    {
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        foreach ([Str::uuid()->toString(), Str::uuid()->toString()] as $key) {
            $this->actingAs($client, 'sanctum')
                ->postJson('/api/v1/conversations/'.$conversation.'/messages', [
                    'body' => 'Je confirme le même détail.',
                    'idempotency_key' => $key,
                ])
                ->assertCreated();
        }

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_conversation_is_reused_per_business_context_not_only_per_participant_pair(): void
    {
        [$client, $professional] = $this->participants();
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
        ]);
        $serviceRequest = ServiceRequest::factory()->create([
            'service_id' => $service->id,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
        ]);

        $generalId = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $requestConversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations', [
                'service_request_id' => $serviceRequest->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $requestConversationAgain = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations', [
                'service_request_id' => $serviceRequest->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertNotSame($generalId, $requestConversation);
        $this->assertSame($requestConversation, $requestConversationAgain);
        $this->assertDatabaseCount('conversations', 2);
        $this->assertDatabaseHas('conversations', [
            'id' => $requestConversation,
            'context_key' => 'request:'.$serviceRequest->id,
        ]);
    }

    public function test_message_attachments_are_private_and_only_conversation_participants_can_download_them(): void
    {
        Storage::fake('local');
        [$client, $professional] = $this->participants();

        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $response = $this->actingAs($client, 'sanctum')
            ->post('/api/v1/conversations/'.$conversation.'/messages', [
                'body' => '',
                'idempotency_key' => (string) Str::uuid(),
                'attachments' => [UploadedFile::fake()->image('plan.jpg')],
            ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.attachments.0.name', 'plan.jpg');
        $attachmentId = $response->json('data.attachments.0.id');
        $attachment = \App\Models\MessageAttachment::query()->findOrFail($attachmentId);
        Storage::disk('local')->assertExists($attachment->path);

        $downloadUrl = $response->json('data.attachments.0.download_url');
        $this->actingAs($client, 'sanctum')->get($downloadUrl)->assertOk();

        $other = User::factory()->create();
        $other->assignRole('client');
        $this->actingAs($other, 'sanctum')
            ->get('/api/v1/conversations/'.$conversation.'/attachments/'.$attachmentId)
            ->assertForbidden();
    }

    public function test_delayed_message_email_is_skipped_when_the_recipient_reads_the_conversation(): void
    {
        [$client, $professional] = $this->participants();
        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');
        $professionalUser = User::query()->findOrFail($professional->user_id);

        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', [
                'body' => 'Message à lire avant l’alerte e-mail.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $delivery = NotificationDelivery::query()
            ->where('user_id', $client->id)
            ->where('channel', 'mail')
            ->firstOrFail();

        $this->assertTrue($delivery->available_at->isFuture());

        $this->travel(3)->minutes();
        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/read')
            ->assertOk();

        (new SendNotificationEmail((int) $delivery->id))->handle();

        $this->assertSame('SKIPPED', $delivery->fresh()->status);
        $this->assertSame('MESSAGE_ALREADY_READ_BEFORE_EMAIL_DELAY', $delivery->fresh()->last_error_code);
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

    public function test_closed_conversation_is_reopened_when_client_returns(): void
    {
        [$client, $professional] = $this->participants();

        $conversationId = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $conversation = Conversation::query()->findOrFail($conversationId);
        $conversation->forceFill(['status' => ConversationStatus::CLOSED])->save();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated();

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'status' => ConversationStatus::OPEN->value,
        ]);
    }

    public function test_blocked_conversation_cannot_be_reopened_by_client(): void
    {
        [$client, $professional] = $this->participants();

        $conversationId = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $conversation = Conversation::query()->findOrFail($conversationId);
        $conversation->forceFill(['status' => ConversationStatus::BLOCKED])->save();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['conversation']);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'status' => ConversationStatus::BLOCKED->value,
        ]);
    }

    public function test_reply_reference_is_returned_and_cannot_cross_conversation_boundaries(): void
    {
        [$client, $professional] = $this->participants();
        $firstConversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $originalId = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$firstConversation.'/messages', [
                'body' => 'Message de référence.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->json('data.id');

        $secondProfessionalUser = User::factory()->create();
        $secondProfessionalUser->assignRole('professional');
        $secondProfessional = ProfessionalProfile::factory()->create([
            'user_id' => $secondProfessionalUser->id,
        ]);
        $secondConversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$secondProfessional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$secondConversation.'/messages', [
                'body' => 'Tentative de réponse croisée.',
                'reply_to_message_id' => $originalId,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reply_to_message_id');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$firstConversation.'/messages', [
                'body' => 'Réponse dans la bonne conversation.',
                'reply_to_message_id' => $originalId,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.reply_to.id', $originalId)
            ->assertJsonPath('data.reply_to.body', 'Message de référence.');
    }

    public function test_read_receipts_respect_the_recipient_privacy_preference(): void
    {
        [$client, $professional] = $this->participants();
        $conversation = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');
        $professionalUser = User::query()->findOrFail($professional->user_id);

        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/messages', [
                'body' => 'Message dont le statut de lecture est privé.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/read')
            ->assertOk();

        $this->actingAs($professionalUser, 'sanctum')
            ->getJson('/api/v1/conversations/'.$conversation.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'read');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/read-receipts', [
                'share_read_receipts' => false,
            ])
            ->assertOk();

        $this->actingAs($professionalUser, 'sanctum')
            ->getJson('/api/v1/conversations/'.$conversation.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'sent');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation.'/read-receipts', [
                'share_read_receipts' => true,
            ])
            ->assertOk();

        $this->actingAs($professionalUser, 'sanctum')
            ->getJson('/api/v1/conversations/'.$conversation.'/messages')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'read');
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
