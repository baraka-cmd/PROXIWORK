<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Moderation;

use Illuminate\Http\Resources\Json\JsonResource;

class ModerationActionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'report_id' => $this->report_id,
            'moderator_id' => $this->moderator_id,
            'action_type' => $this->action_type?->value,
            'reason_code' => $this->reason_code,
            'note' => $this->note,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
