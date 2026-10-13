<?php

declare(strict_types=1);

namespace App\Http\Resources\ProfessionalService;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRealizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'service_id' => $this->service_id,
            'caption' => $this->caption,
            'completed_at' => $this->completed_at?->toDateString(),
            'participation_confirmed' => $this->participation_confirmed,
            'status' => $this->status,
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
