<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailAddressChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $actionUrl = null,
        private readonly ?string $actionLabel = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Bonjour')
            ->line($this->message);

        if ($this->actionUrl !== null && $this->actionLabel !== null) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        return $mail->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message et sécurisez votre compte.');
    }
}
