<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Mail\TransactionalNotificationMail;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = DB::transaction(function (): ?NotificationDelivery {
            $record = NotificationDelivery::query()
                ->lockForUpdate()
                ->findOrFail($this->deliveryId);

            if (in_array($record->status, ['SENT', 'DELIVERED', 'FAILED', 'UNKNOWN', 'SIMULATED'], true)) {
                return null;
            }

            if ($record->status === 'PROCESSING') {
                if ($record->processing_at?->lte(now()->subMinutes(15))) {
                    $record->update([
                        'status' => 'UNKNOWN',
                        'processing_at' => null,
                        'last_error_code' => 'StaleProcessingRequiresReconciliation',
                    ]);
                }

                return null;
            }

            if ($record->available_at !== null && $record->available_at->isFuture()) {
                return null;
            }

            $record->update([
                'status' => 'PROCESSING',
                'attempts' => $record->attempts + 1,
                'processing_at' => now(),
                'queued_at' => null,
                'available_at' => null,
                'last_error_code' => null,
            ]);

            return $record->fresh();
        });

        if ($delivery === null) {
            return;
        }

        $user = User::query()->find($delivery->user_id);
        $notification = DatabaseNotification::query()->find($delivery->notification_id);

        if ($user === null || ! is_string($user->email) || trim($user->email) === '' || $notification === null) {
            $delivery->update([
                'status' => 'FAILED',
                'processing_at' => null,
                'last_error_code' => 'RecipientOrNotificationUnavailable',
            ]);

            return;
        }

        $data = is_array($notification->data) ? $notification->data : [];
        $message = new TransactionalNotificationMail(
            (string) ($data['title'] ?? 'Notification PROXIWORK'),
            (string) ($data['message'] ?? 'Un événement important est disponible dans votre espace PROXIWORK.'),
            is_string($data['url'] ?? null) ? $data['url'] : null,
        );

        if (in_array((string) config('mail.default'), ['log', 'array'], true)) {
            try {
                Mail::to($user->email)->send($message);
                $delivery->update([
                    'status' => 'SIMULATED',
                    'processing_at' => null,
                    'sent_at' => null,
                    'provider_reference' => null,
                    'last_error_code' => 'NON_DELIVERING_MAILER',
                ]);
            } catch (Throwable $exception) {
                $delivery->update([
                    'status' => 'FAILED',
                    'processing_at' => null,
                    'last_error_code' => $exception::class,
                ]);
                throw $exception;
            }

            return;
        }

        try {
            $sentMessage = Mail::to($user->email)->send($message);
        } catch (Throwable $exception) {
            $class = $exception::class;
            $uncertainTransportResult = str_contains($class, 'TransportException')
                || str_contains($class, 'ConnectionException')
                || str_contains($class, 'Timeout');

            $delay = $this->backoff[min(max($delivery->attempts - 1, 0), count($this->backoff) - 1)];

            $delivery->update([
                'status' => $uncertainTransportResult ? 'UNKNOWN' : 'RETRYING',
                'processing_at' => null,
                'available_at' => $uncertainTransportResult ? null : now()->addSeconds($delay),
                'last_error_code' => $class,
            ]);

            Log::warning('PROXIWORK notification email attempt failed.', [
                'delivery_id' => $delivery->getKey(),
                'notification_id' => $delivery->notification_id,
                'recipient_id' => $delivery->user_id,
                'attempt' => $delivery->attempts,
                'status' => $uncertainTransportResult ? 'UNKNOWN' : 'RETRYING',
                'exception' => $class,
            ]);

            // A transport exception can occur after the provider accepted the
            // message but before the application received its acknowledgement.
            // Do not resend an uncertain result automatically.
            if ($uncertainTransportResult) {
                return;
            }

            throw $exception;
        }

        try {
            $delivery->update([
                'status' => 'SENT',
                'processing_at' => null,
                'sent_at' => now(),
                'provider_reference' => is_object($sentMessage) && method_exists($sentMessage, 'getMessageId')
                    ? mb_substr((string) $sentMessage->getMessageId(), 0, 191)
                    : null,
                'last_error_code' => null,
            ]);
        } catch (Throwable $exception) {
            // Do not resend after a successful transport call merely because
            // persisting its acknowledgement failed. The stale-processing
            // reconciler will mark this result UNKNOWN for manual/provider review.
            Log::critical('PROXIWORK could not persist the email acknowledgement.', [
                'delivery_id' => $delivery->getKey(),
                'notification_id' => $delivery->notification_id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);

        if ($delivery === null || in_array($delivery->status, ['SENT', 'DELIVERED', 'UNKNOWN'], true)) {
            return;
        }

        $class = $exception::class;
        $uncertainTransportResult = str_contains($class, 'TransportException')
            || str_contains($class, 'ConnectionException')
            || str_contains($class, 'Timeout');

        $delivery->update([
            'status' => $uncertainTransportResult ? 'UNKNOWN' : 'FAILED',
            'processing_at' => null,
            'available_at' => null,
            'queued_at' => null,
            'last_error_code' => $class,
        ]);
    }
}
