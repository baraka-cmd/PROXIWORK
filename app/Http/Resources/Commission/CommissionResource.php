<?php

declare(strict_types=1);

namespace App\Http\Resources\Commission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'professional_id' => $this->professional_id,
            'gross' => $this->gross_amount,
            'commission_rate' => $this->commission_rate,
            'commission' => $this->commission_amount,
            'net' => $this->net_amount,
            'currency' => $this->currency,
            'calculation_type' => $this->calculation_type,
            'status' => $this->status->value,
            'posted_at' => $this->posted_at?->toISOString(),
            'reversed_at' => $this->reversed_at?->toISOString(),
        ];
    }
}
