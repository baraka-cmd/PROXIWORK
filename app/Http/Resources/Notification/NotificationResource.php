<?php

declare(strict_types=1);

namespace App\\Http\\Resources\\Notification;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];
        $data['url'] = $this->safeInternalUrl(
            is_string($data['url'] ?? null) ? $data['url'] : null,
        );

        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $data,
            'read_at' => $this->read_at?->toISOString(),
            'archived_at' => $this->archived_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function safeInternalUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $application = parse_url((string) config('app.url'));

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\\\')) {
            return $url;
        }

        $target = parse_url($url);

        if (
            ! is_array($target)
            || ! is_array($application)
            || empty($target['host'])
            || empty($application['host'])
            || ! in_array(strtolower((string) ($target['scheme'] ?? 'https')), ['http', 'https'], true)
            || strtolower((string) $target['host']) !== strtolower((string) $application['host'])
        ) {
            return null;
        }

        return $url;
    }
}
