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
        $locales = array_values(array_unique([$locale, 'fr']));
        $variables = [
            'user_name' => $recipient->name,
            'action_url' => $url ?? '',
        ];

        $templates = [];
        foreach (['database', 'mail', 'sms', 'push'] as $channel) {
            $templates[$channel] = NotificationTemplate::query()
                ->where('event_key', $eventKey)
                ->where('channel', $channel)
                ->whereIn('locale', $locales)
                ->where('status', 'published')
                ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])
                ->latest('id')
                ->first();
        }

        $databaseTemplate = $templates['database'];
        $mailTemplate = $templates['mail'];
        $smsTemplate = $templates['sms'];
        $pushTemplate = $templates['push'];

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
            'sms_message' => $smsTemplate
                ? $this->replaceVariables($smsTemplate->body, $variables)
                : null,
            'push_title' => $pushTemplate
                ? $this->replaceVariables($pushTemplate->title, $variables)
                : null,
            'push_message' => $pushTemplate
                ? $this->replaceVariables($pushTemplate->body, $variables)
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
