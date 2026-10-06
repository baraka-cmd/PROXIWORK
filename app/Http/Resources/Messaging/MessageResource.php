<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'conversation_id' => $this->conversation_id,
            'sender' => [
                'id' => $this->sender?->getKey(),
                'name' => $this->sender?->name,
            ],
            'body' => $this->body,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
