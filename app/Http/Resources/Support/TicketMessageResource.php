<?php

declare(strict_types=1);

namespace App\Http\Resources\Support;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->whenLoaded(
                'sender',
                fn () => [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                ]
            ),
            'body' => $this->body,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
