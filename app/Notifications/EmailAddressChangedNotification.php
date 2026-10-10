<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailAddressChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $recipientName,
        private readonly string $newEmail,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre adresse e-mail PROXIWORK a été modifiée')
            ->greeting('Bonjour '.$this->recipientName)
            ->line('L’adresse e-mail de votre compte PROXIWORK vient d’être modifiée.')
            ->line('Nouvelle adresse : '.$this->newEmail)
            ->line('Si vous n’êtes pas à l’origine de cette opération, contactez immédiatement le support et sécurisez votre compte.');
    }
}
