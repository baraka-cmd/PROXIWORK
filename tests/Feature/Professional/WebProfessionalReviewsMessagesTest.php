<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Enums\ReviewStatus;
use App\Models\Conversation;
use App\Models\ProfessionalProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProfessionalReviewsMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_view_own_reviews(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $client = User::factory()->create();

        $review = Review::factory()->create([
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'status' => ReviewStatus::PUBLISHED,
        ]);

        $response = $this->actingAs($professional)->get(route('professional.reviews'));

        $response->assertOk()->assertSee($client->name)->assertSee((string) $review->rating);
    }

    public function test_client_cannot_access_professional_reviews(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $response = $this->actingAs($client)->get(route('professional.reviews'));

        $response->assertForbidden();
    }

    public function test_professional_can_reply_to_published_review(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $client = User::factory()->create();

        $review = Review::factory()->create([
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'status' => ReviewStatus::PUBLISHED,
        ]);

        $response = $this->actingAs($professional)->post(route('professional.reviews.respond', $review), [
            'response' => 'Merci pour votre confiance.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('review_responses', [
            'review_id' => $review->id,
            'professional_id' => $profile->id,
        ]);
    }

    public function test_professional_cannot_access_other_professional_review(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $other = User::factory()->create();
        $other->assignRole('professional');
        $otherProfile = ProfessionalProfile::factory()->create(['user_id' => $other->id]);
        $client = User::factory()->create();

        $review = Review::factory()->create([
            'professional_id' => $otherProfile->id,
            'client_id' => $client->id,
            'status' => ReviewStatus::PUBLISHED,
        ]);

        $this->actingAs($professional)
            ->get(route('professional.reviews'))
            ->assertDontSee($client->name);

        $this->actingAs($professional)
            ->post(route('professional.reviews.respond', $review), [
                'response' => 'Tentative.',
            ])
            ->assertForbidden();
    }

    public function test_professional_can_list_and_send_messages_in_own_conversation(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $conversation = Conversation::factory()->create([
            'type' => ConversationType::CLIENT_PROFESSIONAL,
            'status' => ConversationStatus::OPEN,
            'client_id' => $client->id,
            'professional_id' => $profile->id,
        ]);
        $conversation->participants()->attach([$client->id, $professional->id]);

        $this->actingAs($professional)
            ->get(route('professional.messages'))
            ->assertOk()
            ->assertSee($client->name);

        $this->actingAs($professional)
            ->post(route('professional.messages.store', $conversation), [
                'body' => 'Bonjour, je suis disponible.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $professional->id,
            'body' => 'Bonjour, je suis disponible.',
        ]);
    }

    public function test_professional_cannot_access_other_conversation(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $other = User::factory()->create();
        $other->assignRole('professional');
        $otherProfile = ProfessionalProfile::factory()->create(['user_id' => $other->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $conversation = Conversation::factory()->create([
            'type' => ConversationType::CLIENT_PROFESSIONAL,
            'status' => ConversationStatus::OPEN,
            'client_id' => $client->id,
            'professional_id' => $otherProfile->id,
        ]);
        $conversation->participants()->attach([$client->id, $other->id]);

        $this->actingAs($professional)
            ->get(route('professional.messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($professional)
            ->post(route('professional.messages.store', $conversation), [
                'body' => 'Tentative.',
            ])
            ->assertForbidden();
    }

    public function test_opening_conversation_marks_messages_as_read(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $conversation = Conversation::factory()->create([
            'type' => ConversationType::CLIENT_PROFESSIONAL,
            'status' => ConversationStatus::OPEN,
            'client_id' => $client->id,
            'professional_id' => $profile->id,
        ]);
        $conversation->participants()->attach([$client->id, $professional->id]);

        $message = $conversation->messages()->create([
            'sender_id' => $client->id,
            'body' => 'Bonjour',
        ]);

        $this->actingAs($professional)
            ->get(route('professional.messages.show', $conversation))
            ->assertOk();

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $professional->id,
            'last_read_message_id' => $message->id,
        ]);
    }
}
