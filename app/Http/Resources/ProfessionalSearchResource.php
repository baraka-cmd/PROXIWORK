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
        $address = $this->user->addresses->first();

        return [
            'id' => $this->getKey(),
            'professional_title' => $this->professional_title,
            'name' => $profile !== null
                ? trim($profile->first_name.' '.$profile->last_name)
                : $this->user->name,
            'bio' => $profile?->bio,
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
                'city' => $address?->city,
                'province' => $address?->province,
                'country_code' => $address?->country_code,
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
