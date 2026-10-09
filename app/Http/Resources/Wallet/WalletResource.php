<?php

declare(strict_types=1);

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'available_balance' => $this->available_balance,
            'pending_balance' => $this->pending_balance,
            'locked_balance' => $this->locked_balance,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
