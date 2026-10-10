<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ProfessionalVerificationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProfessionalSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->user->profile;

        return [
            'id' => $this->getKey(),
            'professional_title' => $this->professional_title,
            'business_name' => $this->business_name,
            'name' => filled($this->business_name)
                ? $this->business_name
                : ($profile !== null
                    ? (trim($profile->first_name.' '.$profile->last_name) ?: $this->user->name)
                    : $this->user->name),
            'avatar_url' => filled($profile?->avatar_path)
                ? Storage::disk('public')->url($profile->avatar_path)
                : null,
            'bio' => $this->description ?: $profile?->bio,
            'years_experience' => $this->years_experience,
            'starting_price' => $this->starting_price === null ? null : (float) $this->starting_price,
            'currency' => $this->currency,
            'verification' => [
                // Do not expose internal review states (under review, rejected,
                // needs information). A public badge requires an effective approval.
                'status' => $this->verification_status === ProfessionalVerificationStatus::VERIFIED && $this->verified_at !== null
                    ? 'verified'
                    : 'unverified',
                'verified' => $this->verification_status === ProfessionalVerificationStatus::VERIFIED && $this->verified_at !== null,
            ],
            'availability' => $this->availability_status->value,
            'rating' => [
                'average' => ($this->rating_count ?? 0) > 0 && $this->rating_average !== null
                    ? (float) $this->rating_average
                    : null,
                'count' => (int) ($this->rating_count ?? 0),
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
