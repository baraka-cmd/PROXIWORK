<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ProfessionalVerificationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->user->profile;

        return [
            'id' => $this->getKey(),
            'professional_title' => $this->professional_title,
            'name' => $profile !== null
                ? trim($profile->first_name.' '.$profile->last_name)
                : $this->user->name,
            'bio' => $this->description ?: $profile?->bio,
            'years_experience' => $this->years_experience,
            'starting_price' => $this->starting_price === null ? null : (float) $this->starting_price,
            'currency' => $this->currency,
            'verification' => [
                'status' => $this->verification_status->value,
                'verified' => $this->verification_status === ProfessionalVerificationStatus::VERIFIED,
            ],
            'availability' => $this->availability_status->value,
            'rating' => [
                'average' => (float) $this->rating_average,
                'count' => $this->rating_count,
            ],
            'location' => [
                'city' => $this->city,
                'province' => $this->province,
                'country_code' => config('app.country_code', 'CD'),
                'commune' => $this->commune,
                'service_radius_km' => $this->service_radius_km,
            ],
            'skills' => $this->skills->map(fn ($skill) => [
                'id' => $skill->getKey(),
                'name' => $skill->name,
                'slug' => $skill->slug,
            ])->values(),
            'published_services_count' => $this->published_services_count,
        ];
    }
}
