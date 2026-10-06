<?php

declare(strict_types=1);

namespace App\Http\Resources\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'order_id' => $this->order_id,
            'professional_id' => $this->professional_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status->value,
            'response' => $this->whenLoaded('response', fn () => new ReviewResponseResource($this->response)),
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
