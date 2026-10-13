<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $clientRoute = $user?->hasRole('client') ?? false;

        return [
            'id' => $this->getKey(),
            'conversation_id' => $this->conversation_id,
            'report_url' => $request->is('api/v1/*')
                ? url('/api/v1/conversations/'.$this->conversation_id.'/messages/'.$this->getKey().'/report')
                : route(
                    $clientRoute ? 'client.messages.report' : 'professional.messages.report',
                    [$this->conversation_id, $this->getKey()],
                    true,
                ),
            'sender' => [
                'id' => $this->sender?->getKey(),
                'name' => $this->sender?->name,
            ],
            'body' => $this->body,
            'reply_to' => $this->whenLoaded('replyTo', fn () => $this->replyTo === null ? null : [
                'id' => $this->replyTo->getKey(),
                'sender_name' => $this->replyTo->sender?->name,
                'body' => Str::limit((string) $this->replyTo->body, 160),
            ]),
            'status' => (string) $this->sender_id === (string) $user?->getKey()
                ? (($this->is_read_by_recipient ?? false) ? 'read' : 'sent')
                : 'received',
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment): array => [
                'id' => $attachment->getKey(),
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => (int) $attachment->size,
                'download_url' => $request->is('api/v1/*')
                    ? url('/api/v1/conversations/'.$this->conversation_id.'/attachments/'.$attachment->getKey())
                    : route(
                        $clientRoute ? 'client.messages.attachments.download' : 'professional.messages.attachments.download',
                        $attachment,
                        true,
                    ),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
