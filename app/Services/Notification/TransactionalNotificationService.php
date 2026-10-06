<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\User;
use App\Notifications\AccountActivityNotification;

class TransactionalNotificationService
{
    public function serviceRequestCreated(User $recipient, string $serviceTitle): void
    {
        $this->send(
            $recipient,
            'Nouvelle demande de service',
            'Un client vous a envoyé une nouvelle demande pour le service « '.$serviceTitle.' ».',
            'service_request',
        );
    }

    public function quotationCreated(User $recipient): void
    {
        $this->send($recipient, 'Nouveau devis reçu', 'Le professionnel vous a envoyé une nouvelle proposition commerciale.', 'quotation');
    }

    public function quotationAccepted(User $recipient): void
    {
        $this->send($recipient, 'Devis accepté', 'Le client a accepté votre proposition commerciale.', 'quotation');
    }

    public function quotationRejected(User $recipient): void
    {
        $this->send($recipient, 'Devis refusé', 'Le client a refusé votre proposition commerciale.', 'quotation');
    }

    public function serviceRequestCancelled(User $recipient): void
    {
        $this->send($recipient, 'Demande annulée', 'Une demande de service qui vous était destinée a été annulée par le client.', 'service_request');
    }

    public function counterOfferReceived(User $recipient): void
    {
        $this->send($recipient, 'Nouvelle contre-proposition', 'Une nouvelle proposition commerciale est disponible dans votre négociation.', 'quotation');
    }

    public function paymentSucceeded(User $recipient): void
    {
        $this->send($recipient, 'Paiement confirmé', 'Votre paiement a été confirmé avec succès.', 'payment');
    }

    public function orderConfirmed(User $recipient): void
    {
        $this->send($recipient, 'Commande confirmée', 'Une commande vient d’être confirmée après paiement.', 'order');
    }

    public function messageReceived(User $recipient): void
    {
        $this->send($recipient, 'Nouveau message', 'Vous avez reçu un nouveau message.', 'message');
    }

    public function reviewReceived(User $recipient): void
    {
        $this->send($recipient, 'Nouvel avis', 'Un client vient de publier un avis sur votre prestation.', 'review');
    }

    public function send(User $recipient, string $title, string $message, string $action): void
    {
        $recipient->notify(new AccountActivityNotification($title, $message, $action));
    }
}
