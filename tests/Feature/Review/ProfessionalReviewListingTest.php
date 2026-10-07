<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Enums\ReviewStatus;
use App\Models\ProfessionalProfile;
use App\Models\Review;
use App\Models\User;
use App\Services\Review\ReviewService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
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

        Review::query()->forceCreate([
            'order_id' => null,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'rating' => 5,
            'comment' => 'Excellent.',
            'status' => ReviewStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        Review::query()->forceCreate([
            'order_id' => null,
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'rating' => 3,
            'comment' => 'Correct.',
            'status' => ReviewStatus::HIDDEN,
        ]);

        Review::query()->forceCreate([
            'order_id' => null,
            'client_id' => $client->id,
            'professional_id' => $otherProfessional->id,
            'rating' => 1,
            'comment' => 'Other professional.',
            'status' => ReviewStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $result = app(ReviewService::class)->listForProfessional(
            professional: $professional,
            perPage: 10,
        );

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(2, $result->items());
        $this->assertSame(
            [5, 3],
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

        foreach ([
            [5, ReviewStatus::PUBLISHED, now()->subDays(3)],
            [4, ReviewStatus::PUBLISHED, now()->subDay()],
            [4, ReviewStatus::HIDDEN, now()],
        ] as [$rating, $status, $createdAt]) {
            Review::query()->forceCreate([
                'order_id' => null,
                'client_id' => $client->id,
                'professional_id' => $professional->id,
                'rating' => $rating,
                'comment' => 'Test',
                'status' => $status,
                'published_at' => $status === ReviewStatus::PUBLISHED ? $createdAt : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

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

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->listForProfessional($professional, rating: 6);
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ReviewService::class)->listForProfessional($professional, sort: 'random');
    }
}
