<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Models\Order;
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
        );

        return redirect()
            ->route('client.orders.show', $order)
            ->with('success', 'Merci ! Votre avis a été publié et contribue à une réputation fiable sur PROXIWORK.');
    }
}
