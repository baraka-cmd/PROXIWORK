<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\StoreReviewResponseRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Http\Resources\Review\ReviewResponseResource;
use App\Models\Order;
use App\Models\Review;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    public function store(StoreReviewRequest $request, Order $order): ReviewResource
    {
        $review = $this->reviewService->create(
            order: $order,
            client: $request->user(),
            rating: (int) $request->validated('rating'),
            comment: $request->validated('comment'),
        );

        return new ReviewResource($review);
    }

    public function show(Request $request, Review $review): ReviewResource
    {
        return new ReviewResource(
            $this->reviewService->findForViewer($review, $request->user())
        );
    }

    public function respond(StoreReviewResponseRequest $request, Review $review): JsonResponse
    {
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
