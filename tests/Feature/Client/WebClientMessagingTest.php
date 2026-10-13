<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebClientMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_opening_conversation_marks_messages_read_and_updates_return_only_authorized_messages(): void
    {
        [$client, $professional] = $this->participants();
        $conversation = $this->createConversation($client, $professional);

        $this->actingAs($client)->post(route('client.messages.store', $conversation), [
            'body' => 'Message conservé côté serveur.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('client.messages.show', $conversation));

        $messageId = $conversation->messages()->value('id');

        $this->actingAs($client)
            ->get(route('client.messages.show', $conversation))
            ->assertOk()
            ->assertSee('Message conservé côté serveur.');

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $client->id,
            'last_read_message_id' => $messageId,
        ]);

        $this->actingAs($client)
            ->getJson(route('client.messages.updates', $conversation).'?after_message_id=0&visible=1')
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Message conservé côté serveur.');

        $other = User::factory()->create();
        $other->assignRole('client');

        $this->actingAs($other)
            ->getJson(route('client.messages.updates', $conversation).'?after_message_id=0')
            ->assertForbidden();
    }

    public function test_private_attachment_is_stored_outside_public_disk_and_cannot_be_downloaded_by_another_client(): void
    {
        Storage::fake('local');
        [$client, $professional] = $this->participants();
        $conversation = $this->createConversation($client, $professional);

        $this->actingAs($client)->post(route('client.messages.store', $conversation), [
            'body' => 'Plan de la prestation',
            'idempotency_key' => (string) Str::uuid(),
            'attachments' => [UploadedFile::fake()->image('plan.jpg')],
        ])->assertRedirect(route('client.messages.show', $conversation));

        $attachment = MessageAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('message_attachments', [
            'id' => $attachment->id,
            'disk' => 'local',
            'original_name' => 'plan.jpg',
        ]);

        $other = User::factory()->create();
        $other->assignRole('client');

        $this->actingAs($other)
            ->get(route('client.messages.attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_client_can_pin_and_archive_a_conversation_without_deleting_its_messages(): void
    {
        [$client, $professional] = $this->participants();
        $conversation = $this->createConversation($client, $professional);

        $this->actingAs($client)->post(route('client.messages.pin', $conversation), [
            'pinned' => '1',
        ])->assertRedirect();

        $this->actingAs($client)->post(route('client.messages.store', $conversation), [
            'body' => 'Historique à conserver.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->actingAs($client)->post(route('client.messages.archive', $conversation), [
            'archived' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $client->id,
        ]);

        $participant = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $client->id)
            ->first();

        $this->assertNotNull($participant->pinned_at);
        $this->assertNotNull($participant->archived_at);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Historique à conserver.',
        ]);

        $this->actingAs($client)
            ->get(route('client.messages.index', ['archived' => '1']))
            ->assertOk()
            ->assertSee('Historique à conserver.');
    }

    public function test_client_can_report_a_received_message_and_block_the_conversation(): void
    {
        [$client, $professional] = $this->participants();
        $conversation = $this->createConversation($client, $professional);
        $professionalUser = User::query()->findOrFail($professional->user_id);

        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'body' => 'Contenu à signaler.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $message = $conversation->messages()->firstOrFail();

        $this->actingAs($client)
            ->post(route('client.messages.report', [$conversation, $message]), [
                'reason_code' => 'SPAM',
                'description' => 'Message non sollicité.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $client->id,
            'target_type' => 'message',
            'target_id' => $message->id,
            'reason_code' => 'SPAM',
            'status' => 'pending',
        ]);

        $this->actingAs($client)
            ->post(route('client.messages.block', $conversation))
            ->assertRedirect(route('client.messages.index'));

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => 'blocked',
        ]);

        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'body' => 'Un message après blocage.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertUnprocessable();
    }

    public function test_new_inbound_message_restores_an_archived_conversation_to_the_inbox(): void
    {
        [$client, $professional] = $this->participants();
        $conversation = $this->createConversation($client, $professional);

        $this->actingAs($client)->post(route('client.messages.archive', $conversation), [
            'archived' => '1',
        ])->assertRedirect();

        $this->assertNotNull(DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $client->id)
            ->value('archived_at'));

        $professionalUser = User::query()->findOrFail($professional->user_id);
        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'body' => 'Nouveau message reçu.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->assertNull(DB::table('conversation_participants')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $client->id)
            ->value('archived_at'));
    }

    public function test_inbox_search_finds_older_messages_in_the_conversation_history(): void
    {
        [$client, $professional] = $this->participants();
        $conversation = $this->createConversation($client, $professional);

        $this->actingAs($client)->post(route('client.messages.store', $conversation), [
            'body' => 'Référence technique ALPHA-9281',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $professionalUser = User::query()->findOrFail($professional->user_id);
        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'body' => 'Bonjour, bien reçu.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->actingAs($client)
            ->get(route('client.messages.index', ['q' => 'ALPHA-9281']))
            ->assertOk()
            ->assertSee($professionalUser->name)
            ->assertSee('Bonjour, bien reçu.');
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

    private function createConversation(User $client, ProfessionalProfile $professional): Conversation
    {
        $id = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/professionals/'.$professional->id.'/conversations')
            ->assertCreated()
            ->json('data.id');

        return Conversation::query()->findOrFail($id);
    }
}
