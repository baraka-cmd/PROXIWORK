<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewResponseRequest;
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

        $rating = $request->integer('rating') ?: null;
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
        );

        return back()->with('success', 'Votre réponse a été publiée.');
    }
}
