<?php

declare(strict_types=1);

namespace App\Http\Resources\ProfessionalService;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'pricing_type' => $this->pricing_type->value,
            'price' => $this->price,
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
            'currency' => $this->currency,
            'billing_unit' => $this->billing_unit,
            'service_area' => $this->service_area,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'status' => $this->status->value,
            'sort_order' => $this->sort_order,
            'published_at' => $this->published_at,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->getKey(),
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->getKey(),
                'name' => $skill->name,
                'slug' => $skill->slug,
            ])->values()),
            'images' => ServiceImageResource::collection($this->whenLoaded('images')),
            'professional' => $this->whenLoaded('professionalProfile', fn () => [
                'id' => $this->professionalProfile->getKey(),
                'name' => $this->professionalProfile->user->name,
            ]),
        ];
    }
}
