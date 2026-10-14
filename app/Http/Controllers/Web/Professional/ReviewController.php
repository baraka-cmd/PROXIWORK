<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewResponseRequest;\nuse App\Http\Requests\Admin\Moderation\StoreReportRequest;\nuse App\Enums\ReportReasonCode;\nuse App\Services\Admin\Moderation\ModerationService;
use App\Models\Review;
use App\Services\Review\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request, ReviewService $reviewService): View
    {
        $professional = $request->user()->professionalProfile()->firstOrFail();

        $filters = $request->validate([\n            'rating' => ['nullable', 'integer', 'between:1,5'],\n            'status' => ['nullable', 'in:published,hidden'],\n            'sort' => ['nullable', 'in:latest,oldest,highest,lowest'],\n            'from' => ['nullable', 'date', 'before_or_equal:to'],\n            'to' => ['nullable', 'date', 'after_or_equal:from'],\n            'service_id' => ['nullable', 'integer', 'min:1'],\n            'response' => ['nullable', 'in:answered,unanswered'],\n            'comment' => ['nullable', 'in:with,without'],\n            'search' => ['nullable', 'string', 'max:120'],\n            'period' => ['nullable', 'in:7,30,90'],\n        ]);\n\n        $rating = isset($filters['rating']) ? (int) $filters['rating'] : null;
        $status = $request->filled('status')
            ? ReviewStatus::tryFrom((string) $request->string('status'))
            : null;

        $sort = (string) $request->input('sort', 'latest');

        $reviews = $reviewService->listForProfessional(
            $professional,
            $rating,
            $status,
            $sort,
            15,
        );

        return view('professional.reviews.index', [
            'reviews' => $reviews,
            'rating' => $rating,
            'status' => $status,
            'sort' => $sort,
        ]);
    }

    public function report(\n        StoreReportRequest $request,\n        Review $review,\n        ModerationService $moderationService,\n    ): RedirectResponse {\n        abort_unless($review->professional?->user_id === $request->user()->getKey(), 404);\n\n        $moderationService->createReport(\n            $request->user(),\n            'review',\n            (int) $review->getKey(),\n            ReportReasonCode::from($request->validated('reason_code'))->value,\n            $request->validated('description'),\n            $request,\n        );\n\n        return back()->with('success', 'Votre signalement a été transmis à la modération.');\n    }\n\n    public function respond(
        StoreReviewResponseRequest $request,
        Review $review,
        ReviewService $reviewService,
    ): RedirectResponse {
        $this->authorize('respond', $review);

        $reviewService->respond(
            $review,
            $request->user(),
            (string) $request->string('response'),
        );

        return back()->with('success', 'Votre réponse a été publiée.');
    }
}
