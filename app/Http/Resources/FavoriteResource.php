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
        $user = $professional->user;
        $profile = $user->relationLoaded('profile') ? $user->profile : null;

        return [
            'id' => $this->id,
            'professional_profile_id' => $professional->id,
            'professional' => [
                'id' => $professional->id,
                'name' => $user->name,
                'first_name' => $profile?->first_name,
                'last_name' => $profile?->last_name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
