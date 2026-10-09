<?php

declare(strict_types=1);

namespace App\Http\Resources\ServiceRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'description' => $this->description,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'currency' => $this->currency,
            'desired_at' => $this->desired_at?->toISOString(),
            'status' => $this->status->value,
            'requested_at' => $this->requested_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service->getKey(),
                'title' => $this->service->title,
                'slug' => $this->service->slug,
            ]),
            'professional' => $this->whenLoaded('professional', fn () => [
                'id' => $this->professional->getKey(),
                'name' => $this->professional->user->name,
                'professional_title' => $this->professional->professional_title,
            ]),
            'address' => $this->whenLoaded('address', fn () => $this->address ? [
                'id' => $this->address->getKey(),
                'label' => $this->address->label,
                'city' => $this->address->city,
                'province' => $this->address->province,
                'commune' => $this->address->commune,
                'neighborhood' => $this->address->neighborhood,
                'address_line_1' => $this->address->address_line_1,
            ] : null),
            'status_history' => ServiceRequestStatusHistoryResource::collection(
                $this->whenLoaded('statusHistories')
            ),
        ];
    }
}
