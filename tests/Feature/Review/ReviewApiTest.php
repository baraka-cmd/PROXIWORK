<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Enums\OrderStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_client_can_review_only_a_completed_owned_order_and_rating_is_recalculated(): void
    {
        [$client, $professional, $order] = $this->completedOrderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', [
                'rating' => 5,
                'comment' => 'Excellent service.',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.rating', 5);
        $this->assertDatabaseHas('reviews', [
            'order_id' => $order->id,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'rating' => 5,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('professional_profiles', [
            'id' => $professional->id,
            'rating_average' => '5.00',
            'rating_count' => 1,
        ]);
    }

    public function test_review_is_rejected_before_order_completion(): void
    {
        [$client, , $order] = $this->completedOrderScenario();
        $order->forceFill(['status' => OrderStatus::CONFIRMED])->save();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', [
                'rating' => 5,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_client_cannot_review_someone_elses_order(): void
    {
        [, , $order] = $this->completedOrderScenario();
        $attacker = User::factory()->create();
        $attacker->assignRole('client');

        $this->actingAs($attacker, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', [
                'rating' => 5,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_one_order_can_have_only_one_review(): void
    {
        [$client, , $order] = $this->completedOrderScenario();

        $payload = ['rating' => 4, 'comment' => 'Bon service.'];

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', $payload)
            ->assertCreated();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        [$client, , $order] = $this->completedOrderScenario();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', [
                'rating' => 6,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_only_the_target_professional_can_respond_once(): void
    {
        [$client, $professional, $order] = $this->completedOrderScenario();

        $review = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', [
                'rating' => 3,
                'comment' => 'Service correct.',
            ])
            ->assertCreated()
            ->json('data.id');

        $other = User::factory()->create();
        $other->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $other->id]);

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/reviews/'.$review.'/response', [
                'response' => 'Réponse non autorisée.',
            ])
            ->assertForbidden();

        $professionalUser = User::query()->findOrFail($professional->user_id);

        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/reviews/'.$review.'/response', [
                'response' => 'Merci pour votre retour.',
            ])
            ->assertCreated();

        $this->actingAs($professionalUser, 'sanctum')
            ->postJson('/api/v1/reviews/'.$review.'/response', [
                'response' => 'Deuxième réponse.',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('review_responses', 1);
    }

    public function test_moderator_can_hide_and_republish_a_review_and_rating_follows_public_state(): void
    {
        [$client, , $order] = $this->completedOrderScenario();

        $review = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', ['rating' => 5])
            ->assertCreated()
            ->json('data.id');

        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator, 'sanctum')
            ->postJson('/api/v1/admin/reviews/'.$review.'/moderate', [
                'status' => 'hidden',
                'reason' => 'Contenu signalé pour modération.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('reviews', [
            'id' => $review,
            'status' => 'hidden',
            'moderated_by' => $moderator->id,
        ]);
        $this->assertDatabaseHas('professional_profiles', [
            'id' => $order->professional_id,
            'rating_count' => 0,
            'rating_average' => '0.00',
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson('/api/v1/admin/reviews/'.$review.'/moderate', [
                'status' => 'published',
                'reason' => 'Contenu validé après contrôle.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('professional_profiles', [
            'id' => $order->professional_id,
            'rating_count' => 1,
            'rating_average' => '5.00',
        ]);
    }

    public function test_unrelated_user_cannot_read_review(): void
    {
        [$client, , $order] = $this->completedOrderScenario();

        $review = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/review', [
                'rating' => 5,
            ])
            ->assertCreated()
            ->json('data.id');

        $other = User::factory()->create();
        $other->assignRole('client');

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/v1/reviews/'.$review)
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: ProfessionalProfile, 2: Order}
     */
    private function completedOrderScenario(): array
    {
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create(['user_id' => $professionalUser->id]);

        $client = User::factory()->create();
        $client->assignRole('client');

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

        return [$client, $professional, $order->fresh()];
    }
}
