<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $defaultAddress = $this->user->addresses->first();

        return [
            'id' => $this->getKey(),
            'name' => $this->user->name,
            'profile' => [
                'first_name' => $this->user->profile?->first_name,
                'last_name' => $this->user->profile?->last_name,
                'bio' => $this->user->profile?->bio,
                'avatar_path' => $this->user->profile?->avatar_path,
            ],
            'location' => [
                'city' => $defaultAddress?->city,
                'province' => $defaultAddress?->province,
                'country_code' => $defaultAddress?->country_code,
            ],
            'skills' => $this->skills->map(fn ($skill) => [
                'id' => $skill->getKey(),
                'name' => $skill->name,
                'slug' => $skill->slug,
            ])->values(),
            'services' => $this->services->map(fn ($service) => [
                'id' => $service->getKey(),
                'title' => $service->title,
                'slug' => $service->slug,
                'short_description' => $service->short_description,
                'pricing_type' => $service->pricing_type->value,
                'price' => $service->price,
                'price_min' => $service->price_min,
                'price_max' => $service->price_max,
                'currency' => $service->currency,
                'category' => [
                    'id' => $service->category->getKey(),
                    'name' => $service->category->name,
                    'slug' => $service->category->slug,
                ],
            ])->values(),
        ];
    }
}
