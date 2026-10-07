<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Professional;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProfessionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'account_status' => $this->user?->account_status?->value,
                'email_verified_at' => $this->user?->email_verified_at?->toISOString(),
            ],
            'professional_title' => $this->professional_title,
            'verification_status' => $this->verification_status?->value,
            'availability_status' => $this->availability_status?->value,
            'rating' => [
                'average' => $this->rating_average,
                'count' => $this->rating_count,
            ],
            'counts' => [
                'services' => $this->when(isset($this->services_count), (int) $this->services_count),
                'reviews' => $this->when(isset($this->reviews_count), (int) $this->reviews_count),
                'service_requests' => $this->when(isset($this->service_requests_count), (int) $this->service_requests_count),
            ],
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'proficiency_level' => $skill->pivot?->proficiency_level,
                'years_experience' => $skill->pivot?->years_experience,
            ])->values()),
            'verification_history' => $this->whenLoaded('verificationReviews', fn () => $this->verificationReviews->map(fn ($review) => [
                'id' => $review->id,
                'admin_user_id' => $review->admin_user_id,
                'from_status' => $review->from_status?->value,
                'to_status' => $review->to_status?->value,
                'reason_code' => $review->reason_code,
                'note' => $review->note,
                'created_at' => $review->created_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
