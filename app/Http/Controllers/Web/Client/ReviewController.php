<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\ReportReasonCode;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Moderation\StoreReportRequest;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Models\Order;
use App\Services\Admin\Moderation\ModerationService;
use App\Services\Review\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function store(
        StoreReviewRequest $request,
        Order $order,
        ReviewService $reviewService,
    ): RedirectResponse {
        Gate::authorize('view', $order);

        $reviewService->create(
            order: $order,
            client: $request->user(),
            rating: (int) $request->validated('rating'),
            comment: $request->validated('comment'),
            request: $request,
        );

        return redirect()
            ->route('client.orders.show', $order)
            ->with('success', 'Merci ! Votre avis a été publié et contribue à une réputation fiable sur PROXIWORK.');
    }

    public function reportResponse(
        StoreReportRequest $request,
        Order $order,
        ModerationService $moderationService,
    ): RedirectResponse {
        Gate::authorize('view', $order);

        $order->loadMissing('review.response');
        $reviewResponse = $order->review?->response;

        abort_unless(
            $reviewResponse !== null
                && $order->review?->client_id === $request->user()->getKey()
                && $reviewResponse->status === ReviewStatus::PUBLISHED,
            404,
        );

        $moderationService->createReport(
            $request->user(),
            'review_response',
            (int) $reviewResponse->getKey(),
            ReportReasonCode::from($request->validated('reason_code'))->value,
            $request->validated('description'),
            $request,
        );

        return back()->with('success', 'Votre signalement de la réponse a été transmis à la modération.');
    }

}
