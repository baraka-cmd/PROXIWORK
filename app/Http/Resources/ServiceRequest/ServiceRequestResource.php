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
            'reference' => sprintf('PW-REQ-%06d', $this->getKey()),
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
            'address' => $this->when(
                $this->relationLoaded('addressSnapshot') || $this->relationLoaded('address'),
                function (): ?array {
                    $address = $this->relationLoaded('addressSnapshot') && $this->addressSnapshot !== null
                        ? $this->addressSnapshot
                        : ($this->relationLoaded('address') ? $this->address : null);

                    if ($address === null) {
                        return null;
                    }

                    return [
                        'id' => $this->address_id,
                        'label' => $address->label,
                        'recipient_name' => $address->recipient_name,
                        'contact_phone' => $address->contact_phone,
                        'country_code' => $address->country_code,
                        'city' => $address->city,
                        'province' => $address->province,
                        'commune' => $address->commune,
                        'neighborhood' => $address->neighborhood,
                        'address_line_1' => $address->address_line_1,
                        'address_line_2' => $address->address_line_2,
                        'landmark' => $address->landmark,
                        'access_instructions' => $address->access_instructions,
                        'latitude' => $address->latitude,
                        'longitude' => $address->longitude,
                    ];
                },
            ),
            'status_history' => ServiceRequestStatusHistoryResource::collection(
                $this->whenLoaded('statusHistories')
            ),
        ];
    }
}
