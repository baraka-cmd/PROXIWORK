<?php

declare(strict_types=1);

namespace App\\Notifications;

use App\\Models\\NotificationPreference;
use Illuminate\\Bus\\Queueable;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Notifications\\Messages\\MailMessage;
use Illuminate\\Notifications\\Notification;

class AccountActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $action,
        private readonly ?string $url = null,
        private readonly string $priority = 'important',
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $preferences = $notifiable->notificationPreference()->first();

        if (! $preferences) {
            $preferences = new NotificationPreference([
                'database_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
            ]);
        }

        // The in-app history is a platform record, not an optional delivery
        // channel. User preferences may control external channels only.
        $channels = ['database'];

        // Critical account/security alerts are not disabled by the optional
        // email preference. Callers must use this priority only for real events.
        if ($this->priority === 'critical' || $preferences->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action' => $this->action,
            'url' => $this->url,
            'priority' => $this->priority,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Bonjour '.$notifiable->name)
            ->line($this->message);

        $url = $this->safeInternalUrl($this->url);

        if ($url !== null) {
            $mail->action('Consulter PROXIWORK', $url);
        }

        return $mail;
    }

    private function safeInternalUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $application = parse_url((string) config('app.url'));

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\\\')) {
            return rtrim((string) config('app.url'), '/').$url;
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
