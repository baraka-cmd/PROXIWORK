<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\ReviewStatus;
use App\Http\Requests\Review\ModerateReviewRequest;
use App\Http\Requests\Review\ModerateReviewResponseRequest;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\StoreReviewResponseRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Http\Resources\Review\ReviewResponseResource;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    public function store(StoreReviewRequest $request, Order $order): JsonResponse
    {
        $review = $this->reviewService->create(
            order: $order,
            client: $request->user(),
            rating: (int) $request->validated('rating'),
            comment: $request->validated('comment'),
        );

        return response()->json([
            'message' => 'Avis publié avec succès.',
            'data' => new ReviewResource($review),
            'meta' => [],
        ], 201);
    }

    public function show(Request $request, Review $review): ReviewResource
    {
        $this->authorize('view', $review);

        return new ReviewResource(
            $this->reviewService->findForViewer($review, $request->user())
        );
    }

    public function moderate(ModerateReviewRequest $request, Review $review): ReviewResource
    {
        $review = $this->reviewService->moderate(
            review: $review,
            moderator: $request->user(),
            status: ReviewStatus::from($request->validated('status')),
            reason: $request->validated('reason'),
        );

        return new ReviewResource($review);
    }

    public function moderateResponse(ModerateReviewResponseRequest $request, ReviewResponse $reviewResponse): JsonResponse
    {
        $response = $this->reviewService->moderateResponse(
            response: $reviewResponse,
            moderator: $request->user(),
            status: ReviewStatus::from($request->validated('status')),
            reason: $request->validated('reason'),
        );

        return response()->json([
            'message' => 'Réponse modérée avec succès.',
            'data' => new ReviewResponseResource($response),
            'meta' => [],
        ]);
    }

    public function respond(StoreReviewResponseRequest $request, Review $review): JsonResponse
    {
        $this->authorize('respond', $review);

        $response = $this->reviewService->respond(
            review: $review,
            professionalUser: $request->user(),
            response: $request->validated('response'),
        );

        return response()->json([
            'message' => 'Réponse publiée avec succès.',
            'data' => new ReviewResponseResource($response),
            'meta' => [],
        ], 201);
    }
}
