<?php

declare(strict_types=1);

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PaymentIntentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'order_id' => $this->order_id,
            'status' => $this->status->value,
            'method' => $this->method->value,
            'provider' => $this->provider->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider_reference' => $this->provider_reference,
            'redirect_url' => $this->redirect_url ?? null,
            'instructions' => $this->instructions ?? null,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toISOString() : null,
        ];
    }
}
