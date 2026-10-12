<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $professional = $this->professionalProfile;
        $professionalUser = $professional?->user;
        $profile = $professionalUser?->relationLoaded('profile') ? $professionalUser->profile : null;
        $service = $this->service;

        return [
            'id' => $this->id,
            'type' => $professional !== null ? 'professional' : 'service',
            'professional_profile_id' => $professional?->id,
            'professional' => $professional === null ? null : [
                'id' => $professional->id,
                'name' => $professionalUser?->name,
                'first_name' => $profile?->first_name,
                'last_name' => $profile?->last_name,
            ],
            'service_id' => $service?->id,
            'service' => $service === null ? null : [
                'id' => $service->id,
                'title' => $service->title,
                'slug' => $service->slug,
                'category' => $service->category?->name,
                'professional_profile_id' => $service->professional_profile_id,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
