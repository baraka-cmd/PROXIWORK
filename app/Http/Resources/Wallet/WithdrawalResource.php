<?php

declare(strict_types=1);

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider' => $this->provider,
            'destination' => $this->destination,
            'status' => $this->status?->value ?? $this->status,
            'provider_transaction_id' => $this->provider_transaction_id,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'requested_at' => $this->requested_at?->toISOString(),
            'processing_at' => $this->processing_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
