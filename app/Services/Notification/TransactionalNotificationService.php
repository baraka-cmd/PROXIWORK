<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function quotationCreated(User $recipient, ?string $url = null): void
    {
        $this->send($recipient, 'Nouveau devis reçu', 'Le professionnel vous a envoyé une nouvelle proposition commerciale.', 'quotation', $url);
    }

    public function quotationAccepted(User $recipient, ?string $url = null): void
    {
        $this->send($recipient, 'Devis accepté', 'Le client a accepté votre proposition commerciale.', 'quotation', $url);
    }

    public function quotationRejected(User $recipient, ?string $url = null): void
    {
        $this->send($recipient, 'Devis refusé', 'Le client a refusé votre proposition commerciale.', 'quotation', $url);
    }

    public function clientCounterOfferAccepted(User $recipient, ?string $url = null): void
    {
        $this->send($recipient, 'Contre-proposition acceptée', 'Le professionnel a accepté votre contre-proposition. La commande a été créée et attend la prochaine étape prévue.', 'quotation', $url);
    }

    public function clientCounterOfferRejected(User $recipient, ?string $url = null): void
    {
        $this->send($recipient, 'Contre-proposition refusée', 'Le professionnel a refusé votre contre-proposition. La décision est enregistrée dans l’historique du devis.', 'quotation', $url);
    }

    public function quotationExpired(User $recipient, ?string $url = null): void
    {
        $this->send($recipient, 'Devis expiré', 'La période de validité de cette proposition commerciale est terminée.', 'quotation', $url);
    }

    public function serviceRequestCancelled(User $recipient): void
    {
        $this->send($recipient, 'Demande annulée', 'Une demande de service qui vous était destinée a été annulée par le client.', 'service_request');
    }

    public function serviceRequestRejected(User $recipient): void
    {
        $this->send($recipient, 'Demande refusée', 'Le professionnel a refusé votre demande de service.', 'service_request');
    }

    public function counterOfferReceived(User $recipient, ?string $url = null): void
    {
        $this->send($recipient, 'Nouvelle contre-proposition', 'Une nouvelle proposition commerciale est disponible dans votre négociation.', 'quotation', $url);
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

    private function send(User $recipient, string $title, string $message, string $action, ?string $url = null): void
    {
        try {
            $recipient->notify(new AccountActivityNotification($title, $message, $action, $url));
        } catch (Throwable $exception) {
            // Notifications are secondary effects: a dispatch failure must not turn
            // an already-committed business transition into a false HTTP failure.
            Log::error('PROXIWORK notification dispatch failed.', [
                'recipient_id' => $recipient->getKey(),
                'action' => $action,
                'exception' => $exception::class,
            ]);
        }
    }
}
