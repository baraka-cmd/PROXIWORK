<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'changed_by' => $this->changed_by,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toISOString() : null,
        ];
    }
}
