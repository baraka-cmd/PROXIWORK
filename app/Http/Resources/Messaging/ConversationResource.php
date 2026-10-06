<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $professional = $this->professional;
        $client = $this->client;

        return [
            'id' => $this->getKey(),
            'type' => $this->type->value,
            'status' => $this->status->value,
            'client' => $client ? [
                'id' => $client->getKey(),
                'name' => $client->name,
            ] : null,
            'professional' => $professional ? [
                'id' => $professional->getKey(),
                'user_id' => $professional->user_id,
                'title' => $professional->professional_title,
                'name' => $professional->user?->name,
            ] : null,
            'last_message' => $this->whenLoaded('lastMessage', fn () => $this->lastMessage
                ? new MessageResource($this->lastMessage)
                : null),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
