<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProfessionalReviewsMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_view_own_reviews_and_reply(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $order = $this->createCompletedOrder($client, $professional, $profile);
        $review = Review::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($professional)
            ->get(route('professional.reviews'))
            ->assertOk()
            ->assertSee('Client PROXIWORK')
            ->assertDontSee($client->name)
            ->assertSee('5/5');

        $this->actingAs($professional)
            ->post(route('professional.reviews.respond', $review), [
                'response' => 'Merci pour votre confiance.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('review_responses', [
            'review_id' => $review->id,
            'professional_id' => $profile->id,
            'response' => 'Merci pour votre confiance.',
        ]);
    }

    public function test_professional_isolated_from_other_professional_reviews(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $other = User::factory()->create();
        $other->assignRole('professional');
        $otherProfile = ProfessionalProfile::factory()->create(['user_id' => $other->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $order = $this->createCompletedOrder($client, $other, $otherProfile);
        $review = Review::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($professional)
            ->get(route('professional.reviews'))
            ->assertOk()
            ->assertDontSee($client->name);

        $this->actingAs($professional)
            ->post(route('professional.reviews.respond', $review), [
                'response' => 'Tentative.',
            ])
            ->assertForbidden();
    }

    public function test_client_cannot_access_professional_reviews_or_messages(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get(route('professional.reviews'))->assertForbidden();
        $this->actingAs($client)->get(route('professional.messages'))->assertForbidden();
    }

    public function test_professional_can_list_read_and_send_messages_in_own_conversation(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $conversation = Conversation::query()->create([
            'type' => ConversationType::CLIENT_PROFESSIONAL,
            'status' => ConversationStatus::OPEN,
            'client_id' => $client->id,
            'professional_id' => $profile->id,
        ]);
        $conversation->participants()->attach([$client->id, $professional->id]);

        $message = $conversation->messages()->create([
            'sender_id' => $client->id,
            'body' => 'Bonjour professionnel.',
        ]);

        $this->actingAs($professional)
            ->get(route('professional.messages'))
            ->assertOk()
            ->assertSee($client->name);

        $this->actingAs($professional)
            ->get(route('professional.messages.show', $conversation))
            ->assertOk()
            ->assertSee('Bonjour professionnel.');

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $professional->id,
            'last_read_message_id' => $message->id,
        ]);

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

    public function test_professional_cannot_access_other_professional_conversation(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $other = User::factory()->create();
        $other->assignRole('professional');
        $otherProfile = ProfessionalProfile::factory()->create(['user_id' => $other->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $conversation = Conversation::query()->create([
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

    public function test_client_can_review_a_completed_order_from_the_web_order_detail(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $order = $this->createCompletedOrder($client, $professional, $profile, createReview: false);

        $this->actingAs($client)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee('Évaluer cette prestation')
            ->assertSee('Publier mon avis');

        $this->actingAs($client)
            ->post(route('client.orders.review.store', $order), [
                'rating' => 4,
                'comment' => 'Prestation sérieuse et communication claire.',
            ])
            ->assertRedirect(route('client.orders.show', $order))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'rating' => 4,
            'comment' => 'Prestation sérieuse et communication claire.',
            'status' => ReviewStatus::PUBLISHED->value,
        ]);
    }

    public function test_professional_can_report_an_abusive_review_on_their_own_profile(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $order = $this->createCompletedOrder($client, $professional, $profile);
        $review = Review::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($professional)
            ->post(route('professional.reviews.report', $review), [
                'target_type' => 'review',
                'target_id' => $review->id,
                'reason_code' => 'ABUSIVE_CONTENT',
                'description' => 'Le commentaire contient des insultes ciblées.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $professional->id,
            'target_type' => Review::class,
            'target_id' => $review->id,
            'reason_code' => 'ABUSIVE_CONTENT',
            'status' => 'pending',
        ]);

        $this->actingAs($professional)
            ->post(route('professional.reviews.report', $review), [
                'target_type' => 'review',
                'target_id' => $review->id,
                'reason_code' => 'ABUSIVE_CONTENT',
                'description' => 'Le commentaire contient des insultes ciblées.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('target_id');

        $this->assertDatabaseCount('reports', 1);
    }

    public function test_professional_cannot_report_a_review_belonging_to_another_professional(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $professional->id]);

        $otherProfessional = User::factory()->create();
        $otherProfessional->assignRole('professional');
        $otherProfile = ProfessionalProfile::factory()->create(['user_id' => $otherProfessional->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $order = $this->createCompletedOrder($client, $otherProfessional, $otherProfile);
        $review = Review::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($professional)
            ->post(route('professional.reviews.report', $review), [
                'target_type' => 'review',
                'target_id' => $review->id,
                'reason_code' => 'FRAUD',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('reports', [
            'reporter_id' => $professional->id,
            'target_id' => $review->id,
            'reason_code' => 'FRAUD',
        ]);
    }

    private function createCompletedOrder(
        User $client,
        User $professionalUser,
        ProfessionalProfile $professional,
        bool $createReview = true,
    ): Order {
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'category_id' => Category::factory()->create()->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $address = Address::factory()->default()->create(['user_id' => $client->id]);

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'address_id' => $address->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::QUOTED,
            'requested_at' => now(),
        ]);

        $quotation = Quotation::query()->forceCreate([
            'service_request_id' => $request->id,
            'status' => 'sent',
        ]);

        $offer = $quotation->offers()->forceCreate([
            'created_by' => $professionalUser->id,
            'actor_type' => 'professional',
            'version' => 1,
            'amount' => 500,
            'currency' => 'USD',
            'description' => 'Service professionnel.',
            'duration_value' => 10,
            'duration_unit' => 'days',
            'conditions' => 'Conditions.',
            'valid_until' => now()->addDays(5),
        ]);

        $quotation->forceFill([
            'status' => 'accepted',
            'current_offer_id' => $offer->id,
            'accepted_offer_id' => $offer->id,
            'accepted_at' => now(),
        ])->save();

        $request->forceFill(['status' => ServiceRequestStatus::ACCEPTED])->save();

        $order = app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client);

        $order->forceFill([
            'status' => OrderStatus::COMPLETED,
            'completed_at' => now(),
        ])->save();

        if ($createReview) {
            Review::query()->forceCreate([
                'order_id' => $order->id,
                'client_id' => $client->id,
                'professional_id' => $professional->id,
                'rating' => 5,
                'comment' => 'Excellent travail.',
                'status' => ReviewStatus::PUBLISHED,
                'published_at' => now(),
            ]);
        }

        return $order->fresh();
    }
}
