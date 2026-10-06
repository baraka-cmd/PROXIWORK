<?php

declare(strict_types=1);

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'version' => $this->version,
            'actor_type' => $this->actor_type->value,
            'created_by' => $this->created_by,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'duration_value' => $this->duration_value,
            'duration_unit' => $this->duration_unit->value,
            'conditions' => $this->conditions,
            'valid_until' => $this->valid_until?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
