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
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly TransactionalNotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(Order $order, User $client, int $rating, ?string $comment, ?Request $request = null): Review
    {
        return $this->database->transaction(function () use ($order, $client, $rating, $comment, $request): Review {
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
            $this->auditLogService->record('review.created', $review, $client, ['order_id' => $lockedOrder->getKey()], $request);

            $professionalUser = User::query()->find($professional->user_id);
            if ($professionalUser !== null) {
                $this->notificationService->reviewReceived(
                    $professionalUser,
                    'review-received:'.$review->getKey(),
                );
            }

            return $review->load('response');
        }, attempts: 3);
    }

    public function respond(Review $review, User $professionalUser, string $response, ?Request $request = null): ReviewResponse
    {
        return $this->database->transaction(function () use ($review, $professionalUser, $response, $request): ReviewResponse {
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

            $this->auditLogService->record('review.response_published', $record, $professionalUser, ['review_id' => $lockedReview->getKey()], $request);

            $client = User::query()->find($lockedReview->client_id);
            if ($client !== null) {
                $this->notificationService->reviewResponseReceived(
                    $client,
                    'review-response:'.$record->getKey(),
                );
            }

            return $record;
        }, attempts: 3);
    }

    public function moderate(Review $review, User $moderator, ReviewStatus $status, string $reason, ?Request $request = null): Review
    {
        return $this->database->transaction(function () use ($review, $moderator, $status, $reason, $request): Review {
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
            $this->auditLogService->record('review.moderated', $lockedReview, $moderator, ['status' => $status->value, 'reason' => $reason], $request);

            $professionalUser = User::query()->find($professional->user_id);
            if ($professionalUser !== null) {
                $this->notificationService->reviewModerationUpdated(
                    $professionalUser,
                    $status->value,
                    'review-moderation:'.$lockedReview->getKey().':'.$status->value,
                );
            }

            return $lockedReview->refresh()->load('response');
        }, attempts: 3);
    }

    public function moderateResponse(ReviewResponse $response, User $moderator, ReviewStatus $status, string $reason, ?Request $request = null): ReviewResponse
    {
        return $this->database->transaction(function () use ($response, $moderator, $status, $reason, $request): ReviewResponse {
            $lockedResponse = ReviewResponse::query()->lockForUpdate()->findOrFail($response->getKey());

            $lockedResponse->forceFill([
                'status' => $status,
                'moderated_by' => $moderator->getKey(),
                'moderated_at' => now(),
                'moderation_reason' => $reason,
            ])->save();
            $this->auditLogService->record('review.response_moderated', $lockedResponse, $moderator, ['status' => $status->value, 'reason' => $reason], $request);

            $professional = ProfessionalProfile::query()->find($lockedResponse->professional_id);
            $professionalUser = $professional === null ? null : User::query()->find($professional->user_id);
            if ($professionalUser !== null) {
                $this->notificationService->reviewModerationUpdated(
                    $professionalUser,
                    $status->value,
                    'review-response-moderation:'.$lockedResponse->getKey().':'.$status->value,
                );
            }

            return $lockedResponse->refresh();
        }, attempts: 3);
    }

    /**
     * List all reviews belonging to the given professional.
     *
     * The professional scope is enforced at the domain query level. Both
     * published and hidden reviews remain available to the owner so the
     * professional can see moderation state; public listings can filter to
     * published reviews explicitly.
     */
    public function listForProfessional(
        ProfessionalProfile $professional,
        ?int $rating = null,
        ?ReviewStatus $status = null,
        string $sort = 'latest',
        int $perPage = 15,
        ?string $from = null,
        ?string $to = null,
        ?int $serviceId = null,
        ?bool $hasResponse = null,
        ?bool $hasComment = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            throw ValidationException::withMessages([
                'rating' => 'La note doit être comprise entre 1 et 5.',
            ]);
        }

        $perPage = max(1, min($perPage, 100));

        $query = Review::query()
            ->with([
                'response',
                'order:id,order_number,service_request_id',
                'order.serviceRequest:id,service_id',
                'order.serviceRequest.service:id,title',
            ])
            ->where('professional_id', $professional->getKey());

        if ($rating !== null) {
            $query->where('rating', $rating);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($from !== null) {
            $query->whereDate('published_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('published_at', '<=', $to);
        }

        if ($serviceId !== null) {
            $query->whereHas('order.serviceRequest', fn ($request) => $request->where('service_id', $serviceId));
        }

        if ($hasResponse === true) {
            $query->whereHas('response', fn ($response) => $response->where('status', ReviewStatus::PUBLISHED->value));
        } elseif ($hasResponse === false) {
            $query->whereDoesntHave('response', fn ($response) => $response->where('status', ReviewStatus::PUBLISHED->value));
        }

        if ($hasComment === true) {
            $query->whereNotNull('comment')->whereRaw("TRIM(comment) <> ''");
        } elseif ($hasComment === false) {
            $query->where(fn ($comments) => $comments->whereNull('comment')->orWhereRaw("TRIM(comment) = ''"));
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(fn ($match) => $match
                ->where('comment', 'like', $term)
                ->orWhereHas('order.serviceRequest.service', fn ($service) => $service->where('title', 'like', $term))
            );
        }

        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'highest' => $query->orderByDesc('rating')->orderByDesc('created_at')->orderByDesc('id'),
            'lowest' => $query->orderBy('rating')->orderByDesc('created_at')->orderByDesc('id'),
            'latest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => throw ValidationException::withMessages([
                'sort' => 'Le tri demandé est invalide.',
            ]),
        };

        return $query->paginate($perPage);
    }

    public function reputationSummary(ProfessionalProfile $professional, int $periodDays = 30): array
    {
        $periodDays = max(1, min($periodDays, 365));
        $today = now()->endOfDay();
        $currentStart = now()->startOfDay()->subDays($periodDays - 1);
        $previousStart = $currentStart->copy()->subDays($periodDays);
        $previousEnd = $currentStart->copy()->subSecond();
        $published = Review::query()->where('professional_id', $professional->getKey())->where('status', ReviewStatus::PUBLISHED->value);
        $summary = (clone $published)->selectRaw('COUNT(*) as review_count, AVG(rating) as rating_average')->first();
        $distributionRows = (clone $published)->selectRaw('rating, COUNT(*) as aggregate')->groupBy('rating')->pluck('aggregate', 'rating');
        $current = (clone $published)->whereBetween('published_at', [$currentStart, $today])->selectRaw('COUNT(*) as count, AVG(rating) as average')->first();
        $previous = (clone $published)->whereBetween('published_at', [$previousStart, $previousEnd])->selectRaw('COUNT(*) as count, AVG(rating) as average')->first();
        $answered = (clone $published)->whereHas('response', fn ($response) => $response->where('status', ReviewStatus::PUBLISHED->value))->count();
        $unanswered = (clone $published)->whereDoesntHave('response', fn ($response) => $response->where('status', ReviewStatus::PUBLISHED->value))->count();

        return [
            'average' => $summary->review_count > 0 ? round((float) $summary->rating_average, 2) : null,
            'total' => (int) $summary->review_count,
            'distribution' => collect(range(1, 5))->mapWithKeys(fn (int $rating) => [$rating => (int) ($distributionRows[$rating] ?? 0)])->all(),
            'unanswered' => $unanswered,
            'answered' => $answered,
            'commented' => (clone $published)->whereNotNull('comment')->whereRaw("TRIM(comment) <> ''")->count(),
            'recent_count' => (int) $current->count,
            'recent_average' => $current->count > 0 ? round((float) $current->average, 2) : null,
            'previous_count' => (int) $previous->count,
            'previous_average' => $previous->count > 0 ? round((float) $previous->average, 2) : null,
            'average_change' => $current->count > 0 && $previous->count > 0
                ? round((float) $current->average - (float) $previous->average, 2)
                : null,
            'period_days' => $periodDays,
            'themes' => (int) $summary->review_count >= 5 ? $this->reviewThemes($professional) : ['sample_count' => 0, 'items' => []],
        ];
    }

    private function reviewThemes(ProfessionalProfile $professional, int $sampleLimit = 500): array
    {
        $comments = Review::query()
            ->where('professional_id', $professional->getKey())
            ->where('status', ReviewStatus::PUBLISHED->value)
            ->whereNotNull('comment')
            ->whereRaw("TRIM(comment) <> ''")
            ->latest('published_at')
            ->limit($sampleLimit)
            ->pluck('comment')
            ->map(fn (string $comment): string => mb_strtolower($comment))
            ->values();

        $definitions = [
            'Qualité du travail' => ['qualité', 'qualite', 'finition', 'solide', 'soigné', 'soigne', 'travail bien fait'],
            'Respect des délais' => ['délai', 'delai', 'retard', 'ponctuel', 'à temps', 'a temps', 'rapidement'],
            'Communication' => ['communication', 'répond', 'repond', 'réponse', 'reponse', 'explique', 'contact', 'informe'],
            'Prix et valeur' => ['prix', 'cher', 'coût', 'cout', 'budget', 'rapport qualité', 'rapport qualite'],
            'Propreté et soin' => ['propre', 'propreté', 'proprete', 'nettoyage', 'sale'],
            'Respect des engagements' => ['engagement', 'promis', 'convenu', 'respecté', 'respecte'],
        ];

        $themes = [];
        foreach ($definitions as $label => $keywords) {
            $count = $comments->filter(function (string $comment) use ($keywords): bool {
                foreach ($keywords as $keyword) {
                    if (str_contains($comment, $keyword)) {
                        return true;
                    }
                }

                return false;
            })->count();

            if ($count > 0) {
                $themes[] = [
                    'label' => $label,
                    'mentions' => $count,
                    'percentage' => $comments->isNotEmpty() ? (int) round(($count / $comments->count()) * 100) : 0,
                ];
            }
        }

        usort($themes, fn (array $left, array $right): int => $right['mentions'] <=> $left['mentions']);

        return [
            'sample_count' => $comments->count(),
            'items' => array_slice($themes, 0, 5),
        ];
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
