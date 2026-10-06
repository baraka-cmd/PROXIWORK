<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'service_id' => $this->service_id,
            'service_title' => $this->service_title,
            'service_description' => $this->service_description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'currency' => $this->currency,
            'duration_value' => $this->duration_value,
            'duration_unit' => $this->duration_unit,
            'conditions' => $this->conditions,
        ];
    }
}
