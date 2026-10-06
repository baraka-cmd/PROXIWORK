<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function create(Order $order, User $client, int $rating, ?string $comment): Review
    {
        return $this->database->transaction(function () use ($order, $client, $rating, $comment): Review {
            $lockedOrder = Order::query()
                ->with('professional')
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->client_id !== $client->getKey()) {
                throw ValidationException::withMessages([
                    'order' => 'Vous ne pouvez pas évaluer cette commande.',
                ]);
            }

            if ($lockedOrder->status !== OrderStatus::COMPLETED) {
                throw ValidationException::withMessages([
                    'order' => 'Une commande doit être terminée avant de pouvoir être évaluée.',
                ]);
            }

            $professional = $lockedOrder->professional;

            if ($professional === null) {
                throw ValidationException::withMessages([
                    'order' => 'La commande ne possède pas de professionnel éligible à l’évaluation.',
                ]);
            }

            $existing = Review::query()
                ->where('order_id', $lockedOrder->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'review' => 'Cette commande a déjà été évaluée.',
                ]);
            }

            if ($rating < 1 || $rating > 5) {
                throw ValidationException::withMessages([
                    'rating' => 'La note doit être comprise entre 1 et 5.',
                ]);
            }

            $professional = ProfessionalProfile::query()
                ->lockForUpdate()
                ->findOrFail($professional->getKey());

            $review = Review::query()->forceCreate([
                'order_id' => $lockedOrder->getKey(),
                'client_id' => $client->getKey(),
                'professional_id' => $professional->getKey(),
                'rating' => $rating,
                'comment' => $comment,
                'status' => ReviewStatus::PUBLISHED,
                'published_at' => now(),
            ]);

            $this->refreshProfessionalRating($professional);

            return $review->load('response');
        }, attempts: 3);
    }

    public function respond(Review $review, User $professionalUser, string $response): ReviewResponse
    {
        return $this->database->transaction(function () use ($review, $professionalUser, $response): ReviewResponse {
            $lockedReview = Review::query()
                ->with('professional')
                ->lockForUpdate()
                ->findOrFail($review->getKey());

            $professional = $lockedReview->professional;

            if ($professional === null || $professional->user_id !== $professionalUser->getKey()) {
                throw ValidationException::withMessages([
                    'review' => 'Vous ne pouvez pas répondre à cet avis.',
                ]);
            }

            if ($lockedReview->status !== ReviewStatus::PUBLISHED) {
                throw ValidationException::withMessages([
                    'review' => 'Seul un avis publié peut recevoir une réponse.',
                ]);
            }

            $existing = ReviewResponse::query()
                ->where('review_id', $lockedReview->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'response' => 'Une réponse existe déjà pour cet avis.',
                ]);
            }

            $record = ReviewResponse::query()->forceCreate([
                'review_id' => $lockedReview->getKey(),
                'professional_id' => $professional->getKey(),
                'response' => $response,
                'status' => ReviewStatus::PUBLISHED,
            ]);

            return $record;
        }, attempts: 3);
    }

    public function moderate(Review $review, User $moderator, ReviewStatus $status, string $reason): Review
    {
        return $this->database->transaction(function () use ($review, $moderator, $status, $reason): Review {
            $lockedReview = Review::query()->lockForUpdate()->findOrFail($review->getKey());

            $lockedReview->forceFill([
                'status' => $status,
                'moderated_by' => $moderator->getKey(),
                'moderated_at' => now(),
                'moderation_reason' => $reason,
                'published_at' => $status === ReviewStatus::PUBLISHED
                    ? ($lockedReview->published_at ?? now())
                    : $lockedReview->published_at,
            ])->save();

            $professional = ProfessionalProfile::query()
                ->lockForUpdate()
                ->findOrFail($lockedReview->professional_id);

            $this->refreshProfessionalRating($professional);

            return $lockedReview->refresh()->load('response');
        }, attempts: 3);
    }

    public function moderateResponse(ReviewResponse $response, User $moderator, ReviewStatus $status, string $reason): ReviewResponse
    {
        return $this->database->transaction(function () use ($response, $moderator, $status, $reason): ReviewResponse {
            $lockedResponse = ReviewResponse::query()->lockForUpdate()->findOrFail($response->getKey());

            $lockedResponse->forceFill([
                'status' => $status,
                'moderated_by' => $moderator->getKey(),
                'moderated_at' => now(),
                'moderation_reason' => $reason,
            ])->save();

            return $lockedResponse->refresh();
        }, attempts: 3);
    }

    public function findForViewer(Review $review, User $viewer): Review
    {
        $review->loadMissing('response', 'professional');

        $isClient = $review->client_id === $viewer->getKey();
        $isProfessional = $review->professional?->user_id === $viewer->getKey();

        if (! $isClient && ! $isProfessional) {
            throw ValidationException::withMessages([
                'review' => 'Vous ne pouvez pas consulter cet avis.',
            ]);
        }

        return $review;
    }

    private function refreshProfessionalRating(ProfessionalProfile $professional): void
    {
        $summary = Review::query()
            ->where('professional_id', $professional->getKey())
            ->where('status', ReviewStatus::PUBLISHED->value)
            ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as rating_average')
            ->first();

        $professional->forceFill([
            'rating_count' => (int) $summary->review_count,
            'rating_average' => number_format((float) $summary->rating_average, 2, '.', ''),
        ])->save();
    }
}
