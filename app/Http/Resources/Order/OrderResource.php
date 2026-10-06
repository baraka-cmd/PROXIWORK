<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'service_request_id' => $this->service_request_id,
            'quotation_id' => $this->quotation_id,
            'accepted_offer_id' => $this->accepted_offer_id,
            'client_id' => $this->client_id,
            'professional_id' => $this->professional_id,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'address' => $this->whenLoaded('addressSnapshot', fn () => new OrderAddressSnapshotResource($this->addressSnapshot)),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'accepted_at' => $this->accepted_at ? Carbon::parse($this->accepted_at)->toISOString() : null,
            'confirmed_at' => $this->confirmed_at ? Carbon::parse($this->confirmed_at)->toISOString() : null,
            'started_at' => $this->started_at ? Carbon::parse($this->started_at)->toISOString() : null,
            'completed_at' => $this->completed_at ? Carbon::parse($this->completed_at)->toISOString() : null,
            'cancelled_at' => $this->cancelled_at ? Carbon::parse($this->cancelled_at)->toISOString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toISOString() : null,
        ];
    }
}
