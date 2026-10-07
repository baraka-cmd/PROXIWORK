<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
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
use App\Services\Review\ReviewService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProfessionalReviewListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_professional_review_listing_is_scoped_and_eager_loads_required_relations(): void
    {
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('professional');
        $otherProfessional = ProfessionalProfile::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $this->createOrder($client, $professionalUser, $professional);
        $this->createOrder($client, $professionalUser, $professional);
        $this->createOrder($client, $otherUser, $otherProfessional);

        $professionalReviews = Review::query()
            ->where('professional_id', $professional->id)
            ->orderBy('id')
            ->get();

        $professionalReviews[0]->forceFill([
            'rating' => 5,
            'comment' => 'Excellent.',
            'status' => ReviewStatus::PUBLISHED,
            'published_at' => now(),
        ])->save();

        $professionalReviews[1]->forceFill([
            'rating' => 3,
            'comment' => 'Correct.',
            'status' => ReviewStatus::HIDDEN,
            'published_at' => null,
        ])->save();

        $result = app(ReviewService::class)->listForProfessional(
            professional: $professional,
            perPage: 10,
        );

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(2, $result->items());
        $this->assertSame(
            [3, 5],
            collect($result->items())->pluck('rating')->all(),
        );
        $this->assertTrue($result->items()[0]->relationLoaded('client'));
        $this->assertTrue($result->items()[0]->relationLoaded('response'));
        $this->assertTrue($result->items()[0]->relationLoaded('order'));
    }

    public function test_professional_review_listing_supports_rating_status_and_sort_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $first = $this->createOrder($client, $user, $professional);
        $second = $this->createOrder($client, $user, $professional);
        $third = $this->createOrder($client, $user, $professional);

        Review::query()->where('order_id', $first->id)->update([
            'rating' => 5,
            'status' => ReviewStatus::PUBLISHED->value,
            'published_at' => now()->subDays(3),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        Review::query()->where('order_id', $second->id)->update([
            'rating' => 4,
            'status' => ReviewStatus::PUBLISHED->value,
            'published_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Review::query()->where('order_id', $third->id)->update([
            'rating' => 4,
            'status' => ReviewStatus::HIDDEN->value,
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ReviewService::class);

        $this->assertCount(
            2,
            $service->listForProfessional(
                $professional,
                status: ReviewStatus::PUBLISHED,
                perPage: 10,
            )->items(),
        );

        $highest = $service->listForProfessional(
            $professional,
            sort: 'highest',
            perPage: 10,
        )->items();

        $this->assertSame([5, 4, 4], collect($highest)->pluck('rating')->all());

        $fourStars = $service->listForProfessional(
            $professional,
            rating: 4,
            perPage: 10,
        )->items();

        $this->assertCount(2, $fourStars);
    }

    public function test_invalid_rating_and_sort_are_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $service = app(ReviewService::class);

        try {
            $service->listForProfessional($professional, rating: 6);
            $this->fail('An invalid rating should throw a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rating', $exception->errors());
        }

        try {
            $service->listForProfessional($professional, sort: 'random');
            $this->fail('An invalid sort should throw a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sort', $exception->errors());
        }
    }

    private function createOrder(
        User $client,
        User $professionalUser,
        ProfessionalProfile $professional,
    ): Order {
        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'category_id' => Category::factory()->create()->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $address = Address::factory()->default()->create([
            'user_id' => $client->id,
        ]);

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

        $request->forceFill([
            'status' => ServiceRequestStatus::ACCEPTED,
        ])->save();

        $order = app(OrderService::class)->createFromAcceptedQuotation(
            $quotation->fresh(),
            $client,
        );

        $order->forceFill([
            'status' => OrderStatus::COMPLETED,
            'completed_at' => now(),
        ])->save();

        $review = Review::query()->forceCreate([
            'order_id' => $order->id,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'rating' => 5,
            'status' => ReviewStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        return $order->fresh();
    }
}
