<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderAddressSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'recipient_name' => $this->recipient_name,
            'contact_phone' => $this->contact_phone,
            'country_code' => $this->country_code,
            'province' => $this->province,
            'city' => $this->city,
            'commune' => $this->commune,
            'neighborhood' => $this->neighborhood,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'landmark' => $this->landmark,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
