<?php

declare(strict_types=1);

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'service_request_id' => $this->service_request_id,
            'status' => $this->status->value,
            'current_offer' => $this->whenLoaded('currentOffer', fn () => new QuotationOfferResource($this->currentOffer)),
            'accepted_offer' => $this->whenLoaded('acceptedOffer', fn () => new QuotationOfferResource($this->acceptedOffer)),
            'offers' => QuotationOfferResource::collection($this->whenLoaded('offers')),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->type,
                'offer_id' => $event->offer_id,
                'actor_id' => $event->actor_id,
                'metadata' => $event->metadata,
                'created_at' => $event->created_at?->toISOString(),
            ])),
            'accepted_at' => $this->accepted_at ? Carbon::parse($this->accepted_at)->toISOString() : null,
            'rejected_at' => $this->rejected_at ? Carbon::parse($this->rejected_at)->toISOString() : null,
            'withdrawn_at' => $this->withdrawn_at ? Carbon::parse($this->withdrawn_at)->toISOString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toISOString() : null,
        ];
    }
}
