<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $action,
    ) {}

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

        $channels = [];

        if ($preferences->database_enabled) {
            $channels[] = 'database';
        }

        if ($preferences->email_enabled) {
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
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Bonjour '.$notifiable->name)
            ->line($this->message);
    }
}
