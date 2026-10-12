<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
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

    public function messageReceived(
        User $recipient,
        ?string $idempotencyKey = null,
        ?string $url = null,
    ): void {
        $this->send($recipient, 'Nouveau message', 'Vous avez reçu un nouveau message.', 'message', $url, 'important', $idempotencyKey);
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

    public function accountActivity(
        User $recipient,
        string $title,
        string $message,
        ?string $url = null,
        ?string $idempotencyKey = null,
        string $priority = 'normal',
    ): void {
        $this->send($recipient, $title, $message, 'account', $url, $priority, $idempotencyKey);
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
            // Serialize concurrent attempts for the same recipient so the
            // deduplication lookup and insert cannot race each other.
            User::query()->whereKey($recipient->getKey())->lockForUpdate()->firstOrFail();

            $notification = $deduplicationKey !== null
                ? $recipient->notifications()->where('deduplication_key', $deduplicationKey)->first()
                : null;

            if ($notification === null) {
                $notification = new DatabaseNotification;
                $notification->forceFill([
                    'id' => (string) Str::uuid(),
                    'type' => AccountActivityNotification::class,
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

            if ($emailEnabled) {
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
            }

            $smsEligible = $priority === 'critical' || in_array($action, ['quotation', 'payment', 'order'], true);
            $smsEnabled = $priority === 'critical' || ($preference?->sms_enabled ?? false);

            if ($smsEnabled && $smsEligible) {
                $phone = (string) $recipient->profile()->value('phone');
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                $recipientHint = $digits === '' ? null : '***'.substr($digits, -4);
                $localSimulation = app()->environment(['local', 'testing']);
                $providerConfigured = trim((string) config('services.twilio.account_sid')) !== ''
                    && trim((string) config('services.twilio.auth_token')) !== ''
                    && trim((string) config('services.twilio.from')) !== '';
                $status = $recipientHint === null
                    ? 'FAILED'
                    : ($localSimulation ? 'SIMULATED' : ($providerConfigured ? 'PENDING' : 'FAILED'));
                $errorCode = match ($status) {
                    'SIMULATED' => 'SMS_PROVIDER_NOT_CONFIGURED',
                    'PENDING' => null,
                    default => $recipientHint === null ? 'RECIPIENT_PHONE_UNAVAILABLE' : 'SMS_PROVIDER_NOT_CONFIGURED',
                };

                NotificationDelivery::query()->firstOrCreate(
                    [
                        'notification_id' => $notification->id,
                        'channel' => 'sms',
                    ],
                    [
                        'user_id' => $recipient->getKey(),
                        'recipient_hint' => $recipientHint,
                        'status' => $status,
                        'attempts' => 0,
                        'available_at' => $status === 'PENDING' ? now() : null,
                        'last_error_code' => $errorCode,
                    ],
                );
            }
            $pushEnabled = $priority === 'critical' || ($preference?->push_enabled ?? false);

            if ($pushEnabled) {
                $firebaseConfigured = trim((string) config('services.firebase.project_id')) !== ''
                    && trim((string) config('services.firebase.client_email')) !== ''
                    && trim((string) config('services.firebase.private_key')) !== '';
                $hasDevices = NotificationDevice::query()
                    ->where('user_id', $recipient->getKey())
                    ->exists();
                $localSimulation = app()->environment(['local', 'testing']);
                $pushStatus = ! $hasDevices
                    ? 'FAILED'
                    : ($localSimulation ? 'SIMULATED' : ($firebaseConfigured ? 'PENDING' : 'FAILED'));
                $pushError = match ($pushStatus) {
                    'SIMULATED' => 'PUSH_PROVIDER_NOT_CONFIGURED',
                    'PENDING' => null,
                    default => ! $hasDevices ? 'PUSH_NO_REGISTERED_DEVICES' : 'PUSH_PROVIDER_NOT_CONFIGURED',
                };

                NotificationDelivery::query()->firstOrCreate(
                    [
                        'notification_id' => $notification->id,
                        'channel' => 'push',
                    ],
                    [
                        'user_id' => $recipient->getKey(),
                        'status' => $pushStatus,
                        'attempts' => 0,
                        'available_at' => $pushStatus === 'PENDING' ? now() : null,
                        'last_error_code' => $pushError,
                    ],
                );
            }

        });
    }
}
