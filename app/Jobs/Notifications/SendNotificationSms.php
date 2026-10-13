<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationSms implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $deliveryId,
    ) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()
            ->with(['user.profile', 'notification'])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->channel !== 'sms') {
            return;
        }

        if (in_array($delivery->status, ['SENT', 'DELIVERED', 'SIMULATED', 'SKIPPED', 'UNKNOWN'], true)) {
            return;
        }

        $claimed = NotificationDelivery::query()
            ->whereKey($delivery->getKey())
            ->whereIn('status', ['PENDING', 'RETRYING'])
            ->update([
                'status' => 'PROCESSING',
                'processing_at' => now(),
                'attempts' => $delivery->attempts + 1,
                'last_error_code' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        $delivery->refresh();
        $notification = $delivery->notification;
        $data = is_array($notification?->data) ? $notification->data : [];
        $priority = (string) ($data['priority'] ?? 'normal');
        $preferences = $delivery->user?->notificationPreference()->first();

        if ($priority !== 'critical' && $preferences !== null && ! $preferences->sms_enabled) {
            $this->finish($delivery, 'SKIPPED', 'SMS_DISABLED_BY_PREFERENCE');

            return;
        }

        $accountSid = trim((string) config('services.twilio.account_sid'));
        $authToken = trim((string) config('services.twilio.auth_token'));
        $from = trim((string) config('services.twilio.from'));

        if ($accountSid === '' || $authToken === '' || $from === '') {
            $this->finish($delivery, 'FAILED', 'SMS_PROVIDER_NOT_CONFIGURED');

            return;
        }

        $rawPhone = (string) ($delivery->user?->profile?->phone ?? '');
        $phone = $this->normalizePhone($rawPhone);

        if ($phone === null) {
            $this->finish($delivery, 'FAILED', 'RECIPIENT_PHONE_INVALID');

            return;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $delivery->forceFill(['recipient_hint' => '***'.substr($digits, -4)])->save();

        $action = (string) ($data['action'] ?? 'notification');
        $title = match ($action) {
            'message' => 'Nouveau message',
            'payment' => 'Mise à jour de paiement',
            'quotation' => 'Mise à jour de devis',
            'order' => 'Mise à jour de commande',
            default => 'Alerte importante',
        };
        $body = mb_substr('PROXIWORK : '.$title.'. Connectez-vous à votre compte pour consulter les détails.', 0, 300);

        try {
            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->timeout(12)
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.$accountSid.'/Messages.json', [
                    'To' => $phone,
                    'From' => $from,
                    'Body' => $body,
                ]);

            $this->handleProviderResponse($delivery, $response);
        } catch (ConnectionException) {
            // A timeout can happen after the provider accepted the request.
            // Do not automatically resend an ambiguous SMS and risk duplicates.
            $this->finish($delivery, 'UNKNOWN', 'SMS_PROVIDER_RESULT_UNKNOWN');
        } catch (Throwable $exception) {
            Log::error('PROXIWORK SMS delivery failed unexpectedly.', [
                'delivery_id' => $delivery->getKey(),
                'exception' => $exception::class,
            ]);
            $this->finish($delivery, 'FAILED', 'SMS_PROVIDER_UNEXPECTED_ERROR');
        }
    }

    private function handleProviderResponse(NotificationDelivery $delivery, Response $response): void
    {
        if ($response->successful()) {
            $sid = $response->json('sid');
            $delivery->forceFill([
                'status' => 'SENT',
                'processing_at' => null,
                'sent_at' => now(),
                'provider_reference' => is_string($sid) ? $sid : null,
                'last_error_code' => null,
            ])->save();

            return;
        }

        $status = $response->status();
        if (($status === 429 || $status >= 500) && $delivery->attempts < 5) {
            $delaySeconds = min(3600, 60 * (2 ** max(0, $delivery->attempts - 1)));
            $delivery->forceFill([
                'status' => 'RETRYING',
                'processing_at' => null,
                'queued_at' => null,
                'available_at' => now()->addSeconds($delaySeconds),
                'last_error_code' => 'SMS_PROVIDER_HTTP_'.$status,
            ])->save();

            return;
        }

        $delivery->forceFill([
            'status' => 'FAILED',
            'processing_at' => null,
            'last_error_code' => 'SMS_PROVIDER_HTTP_'.$status,
        ])->save();
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[\s().-]+/', '', $phone) ?? '';

        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1) {
            return $phone;
        }

        $countryCode = preg_replace('/\D+/', '', (string) config('services.twilio.default_country_code', '243')) ?? '';
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($countryCode === '' || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, $countryCode)) {
            // Already contains the configured country code but omitted '+'.
        } else {
            return null;
        }

        $normalized = '+'.$countryCode.$digits;

        return preg_match('/^\+[1-9]\d{7,14}$/', $normalized) === 1 ? $normalized : null;
    }

    private function finish(NotificationDelivery $delivery, string $status, string $errorCode): void
    {
        $delivery->forceFill([
            'status' => $status,
            'processing_at' => null,
            'last_error_code' => $errorCode,
        ])->save();
    }
}
