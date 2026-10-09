<?php

declare(strict_types=1);

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'provider_transaction_id' => $this->provider_transaction_id,
            'provider_reference' => $this->provider_reference,
            'status' => $this->status?->value ?? $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'initiated_at' => $this->initiated_at?->toISOString(),
            'processing_at' => $this->processing_at?->toISOString(),
            'processed_at' => $this->processed_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
