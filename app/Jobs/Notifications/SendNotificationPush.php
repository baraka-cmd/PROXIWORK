<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use App\Services\Notification\FirebasePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $deliveryId,
    ) {}

    public function handle(FirebasePushService $pushService): void
    {
        $delivery = NotificationDelivery::query()
            ->with(['user.notificationPreference', 'notification'])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->channel !== 'push') {
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
        $data = is_array($delivery->notification?->data) ? $delivery->notification->data : [];
        $priority = (string) ($data['priority'] ?? 'normal');
        $preferences = $delivery->user?->notificationPreference;

        if ($priority !== 'critical' && $preferences !== null && ! $preferences->push_enabled) {
            $this->finish($delivery, 'SKIPPED', 'PUSH_DISABLED_BY_PREFERENCE');

            return;
        }

        $projectId = trim((string) config('services.firebase.project_id'));
        $clientEmail = trim((string) config('services.firebase.client_email'));
        $privateKey = trim((string) config('services.firebase.private_key'));

        if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
            $this->finish($delivery, 'FAILED', 'PUSH_PROVIDER_NOT_CONFIGURED');

            return;
        }

        $devices = NotificationDevice::query()
            ->where('user_id', $delivery->user_id)
            ->get();

        if ($devices->isEmpty()) {
            $this->finish($delivery, 'FAILED', 'PUSH_NO_REGISTERED_DEVICES');

            return;
        }

        $action = (string) ($data['action'] ?? 'notification');
        $title = (string) ($data['title'] ?? 'PROXIWORK');
        $body = $action === 'message'
            ? 'Vous avez reçu un nouveau message. Ouvrez PROXIWORK pour le consulter.'
            : mb_substr((string) ($data['message'] ?? 'Vous avez une nouvelle notification.'), 0, 160);
        $payload = [
            'title' => mb_substr($title, 0, 80),
            'body' => $body,
            'data' => [
                'notification_id' => (string) $delivery->notification_id,
                'action' => $action,
                'url' => (string) ($data['url'] ?? ''),
            ],
        ];

        $successCount = 0;
        $providerReferences = [];
        $hasTransientFailure = false;

        try {
            foreach ($devices as $device) {
                $response = $pushService->send($device->token, $payload);

                if ($response->successful()) {
                    $successCount++;
                    $reference = $response->json('name');
                    if (is_string($reference)) {
                        $providerReferences[] = $reference;
                    }
                    $device->forceFill(['last_used_at' => now()])->save();

                    continue;
                }

                $deviceErrorCode = strtoupper((string) ($response->json('error.details.0.errorCode') ?? ''));
                if ($deviceErrorCode === 'UNREGISTERED') {
                    $device->delete();

                    continue;
                }

                if ($response->status() === 429 || $response->status() >= 500) {
                    $hasTransientFailure = true;
                }
            }
        } catch (ConnectionException) {
            // A request can have reached FCM before the connection failed.
            $this->finish($delivery, 'UNKNOWN', 'PUSH_PROVIDER_RESULT_UNKNOWN');

            return;
        } catch (Throwable $exception) {
            Log::error('PROXIWORK push delivery failed unexpectedly.', [
                'delivery_id' => $delivery->getKey(),
                'exception' => $exception::class,
            ]);
            $this->finish($delivery, 'FAILED', 'PUSH_PROVIDER_UNEXPECTED_ERROR');

            return;
        }

        if ($successCount > 0) {
            $delivery->forceFill([
                'status' => 'SENT',
                'processing_at' => null,
                'sent_at' => now(),
                'provider_reference' => $providerReferences[0] ?? null,
                'last_error_code' => null,
            ])->save();

            return;
        }

        if ($hasTransientFailure && $delivery->attempts < 5) {
            $delaySeconds = min(3600, 60 * (2 ** max(0, $delivery->attempts - 1)));
            $delivery->forceFill([
                'status' => 'RETRYING',
                'processing_at' => null,
                'queued_at' => null,
                'available_at' => now()->addSeconds($delaySeconds),
                'last_error_code' => 'PUSH_PROVIDER_TEMPORARY_FAILURE',
            ])->save();

            return;
        }

        $this->finish($delivery, 'FAILED', 'PUSH_PROVIDER_REJECTED_ALL_DEVICES');
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
