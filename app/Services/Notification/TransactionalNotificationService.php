<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionalNotificationService
{
    public function serviceRequestCreated(User $recipient, string $serviceTitle, ?string $idempotencyKey = null): void
    {
        $this->send(
            $recipient,
            'Nouvelle demande de service',
            'Un client vous a envoyé une nouvelle demande pour le service « '.$serviceTitle.' ». ',
            'service_request',
            null,
            'normal',
            $idempotencyKey,
        );
    }

    public function quotationCreated(User $recipient, ?string $url = null, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Nouveau devis reçu', 'Le professionnel vous a envoyé une nouvelle proposition commerciale.', 'quotation', $url, 'important', $idempotencyKey);
    }

    public function quotationAccepted(User $recipient, ?string $url = null, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Devis accepté', 'Le client a accepté votre proposition commerciale.', 'quotation', $url, 'important', $idempotencyKey);
    }

    public function quotationRejected(User $recipient, ?string $url = null, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Devis refusé', 'Le client a refusé votre proposition commerciale.', 'quotation', $url, 'important', $idempotencyKey);
    }

    public function clientCounterOfferAccepted(User $recipient, ?string $url = null, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Contre-proposition acceptée', 'Le professionnel a accepté votre contre-proposition. La commande a été créée et attend la prochaine étape prévue.', 'quotation', $url, 'important', $idempotencyKey);
    }

    public function clientCounterOfferRejected(User $recipient, ?string $url = null, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Contre-proposition refusée', 'Le professionnel a refusé votre contre-proposition. La décision est enregistrée dans l’historique du devis.', 'quotation', $url, 'important', $idempotencyKey);
    }

    public function quotationExpired(User $recipient, ?string $url = null, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Devis expiré', 'La période de validité de cette proposition commerciale est terminée.', 'quotation', $url, 'normal', $idempotencyKey);
    }

    public function serviceRequestCancelled(User $recipient, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Demande annulée', 'Une demande de service qui vous était destinée a été annulée par le client.', 'service_request', null, 'normal', $idempotencyKey);
    }

    public function serviceRequestRejected(User $recipient, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Demande refusée', 'Le professionnel a refusé votre demande de service.', 'service_request', null, 'important', $idempotencyKey);
    }

    public function counterOfferReceived(User $recipient, ?string $url = null, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Nouvelle contre-proposition', 'Une nouvelle proposition commerciale est disponible dans votre négociation.', 'quotation', $url, 'important', $idempotencyKey);
    }

    public function paymentSucceeded(User $recipient, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Paiement confirmé', 'Votre paiement a été confirmé avec succès.', 'payment', null, 'important', $idempotencyKey);
    }

    public function orderConfirmed(User $recipient, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Commande confirmée', 'Une commande vient d’être confirmée après paiement.', 'order', null, 'important', $idempotencyKey);
    }

    public function messageReceived(User $recipient, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Nouveau message', 'Vous avez reçu un nouveau message.', 'message', null, 'important', $idempotencyKey);
    }

    public function reviewReceived(User $recipient, ?string $idempotencyKey = null): void
    {
        $this->send($recipient, 'Nouvel avis', 'Un client vient de publier un avis sur votre prestation.', 'review', null, 'normal', $idempotencyKey);
    }

    public function securityAlert(
        User $recipient,
        string $title,
        string $message,
        ?string $url = null,
        ?string $idempotencyKey = null,
    ): void {
        $this->send($recipient, $title, $message, 'account', $url, 'critical', $idempotencyKey);
    }

    private function send(
        User $recipient,
        string $title,
        string $message,
        string $action,
        ?string $url = null,
        string $priority = 'normal',
        ?string $idempotencyKey = null,
    ): void {
        // URLs identify the underlying business resource when available. Callers
        // should pass an explicit event key for events without a stable resource URL.
        $eventKey = $idempotencyKey ?? $url;
        $deduplicationKey = $eventKey === null
            ? null
            : hash('sha256', $recipient->getKey().'|'.$action.'|'.$eventKey);

        DB::transaction(function () use (
            $recipient,
            $title,
            $message,
            $action,
            $url,
            $priority,
            $deduplicationKey,
        ): void {
            $notificationQuery = $recipient->notifications();

            if ($deduplicationKey !== null) {
                $notificationQuery->where('deduplication_key', $deduplicationKey);
            }

            $notification = $notificationQuery->first();

            if ($notification === null) {
                $notification = new DatabaseNotification();
                $notification->forceFill([
                    'id' => (string) Str::uuid(),
                    'type' => \App\Notifications\AccountActivityNotification::class,
                    'data' => [
                        'title' => $title,
                        'message' => trim($message),
                        'action' => $action,
                        'url' => $url,
                        'priority' => $priority,
                    ],
                    'read_at' => null,
                    'archived_at' => null,
                    'deduplication_key' => $deduplicationKey,
                ]);

                $recipient->notifications()->save($notification);
            }

            $preference = $recipient->notificationPreference()->first();
            $emailEnabled = $priority === 'critical' || ($preference?->email_enabled ?? true);

            if (! $emailEnabled) {
                return;
            }

            $validEmail = is_string($recipient->email)
                && filter_var($recipient->email, FILTER_VALIDATE_EMAIL) !== false;

            NotificationDelivery::query()->firstOrCreate(
                [
                    'notification_id' => $notification->id,
                    'channel' => 'mail',
                ],
                [
                    'user_id' => $recipient->getKey(),
                    'status' => $validEmail ? 'PENDING' : 'FAILED',
                    'attempts' => 0,
                    'available_at' => $validEmail ? now() : null,
                    'last_error_code' => $validEmail ? null : 'InvalidRecipientEmail',
                ],
            );
        });
    }
}
