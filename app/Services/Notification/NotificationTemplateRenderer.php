<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\NotificationTemplate;
use App\Models\User;

class NotificationTemplateRenderer
{
    public function render(
        User $recipient,
        string $eventKey,
        ?string $url,
        string $fallbackTitle,
        string $fallbackMessage,
    ): array {
        $locale = app()->getLocale();
        $variables = [
            'user_name' => $recipient->name,
            'action_url' => $url ?? '',
        ];

        $databaseTemplate = NotificationTemplate::query()
            ->where('event_key', $eventKey)
            ->where('channel', 'database')
            ->where('locale', $locale)
            ->where('status', 'published')
            ->latest('id')
            ->first();

        $mailTemplate = NotificationTemplate::query()
            ->where('event_key', $eventKey)
            ->where('channel', 'mail')
            ->where('locale', $locale)
            ->where('status', 'published')
            ->first();

        return [
            'title' => $databaseTemplate
                ? $this->replaceVariables($databaseTemplate->title, $variables)
                : $fallbackTitle,
            'message' => $databaseTemplate
                ? $this->replaceVariables($databaseTemplate->body, $variables)
                : $fallbackMessage,
            'email_subject' => $mailTemplate
                ? $this->replaceVariables($mailTemplate->subject ?: $mailTemplate->title, $variables)
                : null,
            'email_message' => $mailTemplate
                ? $this->replaceVariables($mailTemplate->body, $variables)
                : null,
        ];
    }

    private function replaceVariables(string $content, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            static fn (array $match): string => (string) ($variables[$match[1]] ?? ''),
            $content,
        ) ?? $content;
    }
}
