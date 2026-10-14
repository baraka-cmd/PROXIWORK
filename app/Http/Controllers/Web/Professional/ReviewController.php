<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\ReportReasonCode;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Moderation\StoreReportRequest;
use App\Http\Requests\Review\StoreReviewResponseRequest;
use App\Models\Review;
use App\Services\Admin\Moderation\ModerationService;
use App\Services\Review\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request, ReviewService $reviewService): View
    {
        $professional = $request->user()->professionalProfile()->firstOrFail();

        $filters = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['nullable', 'in:published,hidden'],
            'sort' => ['nullable', 'in:latest,oldest,highest,lowest'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'response' => ['nullable', 'in:answered,unanswered'],
            'comment' => ['nullable', 'in:with,without'],
            'search' => ['nullable', 'string', 'max:120'],
            'period' => ['nullable', 'in:7,30,90'],
        ]);

        if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages([
                'from' => 'La date de début doit être antérieure ou égale à la date de fin.',
            ]);
        }

        $rating = isset($filters['rating']) ? (int) $filters['rating'] : null;
        $status = isset($filters['status']) ? ReviewStatus::tryFrom($filters['status']) : null;

        $sort = $filters['sort'] ?? 'latest';

        $reviews = $reviewService->listForProfessional(
            $professional,
            $rating,
            $status,
            $sort,
            15,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            isset($filters['service_id']) ? (int) $filters['service_id'] : null,
            isset($filters['response']) ? $filters['response'] === 'answered' : null,
            isset($filters['comment']) ? $filters['comment'] === 'with' : null,
            $filters['search'] ?? null,
        )->withQueryString();

        $services = $professional->services()->orderBy('title')->get(['id', 'title']);
        $summary = $reviewService->reputationSummary($professional, (int) ($filters['period'] ?? 30));

        return view('professional.reviews.index', [
            'reviews' => $reviews,
            'rating' => $rating,
            'status' => $status,
            'sort' => $sort,
            'filters' => $filters,
            'services' => $services,
            'summary' => $summary,
        ]);
    }

    public function report(
        StoreReportRequest $request,
        Review $review,
        ModerationService $moderationService,
    ): RedirectResponse {
        abort_unless($review->professional?->user_id === $request->user()->getKey(), 404);

        $moderationService->createReport(
            $request->user(),
            'review',
            (int) $review->getKey(),
            ReportReasonCode::from($request->validated('reason_code'))->value,
            $request->validated('description'),
            $request,
        );

        return back()->with('success', 'Votre signalement a été transmis à la modération.');
    }

    public function respond(
        StoreReviewResponseRequest $request,
        Review $review,
        ReviewService $reviewService,
    ): RedirectResponse {
        $this->authorize('respond', $review);

        $reviewService->respond(
            $review,
            $request->user(),
            (string) $request->string('response'),
            $request,
        );

        return back()->with('success', 'Votre réponse a été publiée.');
    }
}
