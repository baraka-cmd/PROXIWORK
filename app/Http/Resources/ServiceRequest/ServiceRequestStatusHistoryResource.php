<?php

declare(strict_types=1);

namespace App\Http\Resources\ServiceRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'reason' => $this->reason,
            'changed_by' => $this->whenLoaded('actor', fn () => [
                'id' => $this->actor->getKey(),
                'name' => $this->actor->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
