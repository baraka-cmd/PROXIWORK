<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingEmailChangeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $confirmationUrl,
        private readonly string $recipientName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirmez votre nouvelle adresse e-mail PROXIWORK')
            ->greeting('Bonjour '.$this->recipientName)
            ->line('Une demande de changement d’adresse e-mail a été effectuée sur votre compte PROXIWORK.')
            ->line('Votre adresse actuelle reste inchangée tant que vous n’avez pas confirmé cette demande.')
            ->action('Confirmer la nouvelle adresse', $this->confirmationUrl)
            ->line('Ce lien expire dans une heure. Si vous n’avez pas demandé ce changement, ignorez ce message et sécurisez votre compte.');
    }
}
