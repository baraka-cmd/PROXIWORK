<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Notifications\SendNotificationEmail;
use App\Jobs\Notifications\SendNotificationPush;
use App\Jobs\Notifications\SendNotificationSms;
use App\Models\NotificationDelivery;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:PENDING,RETRYING,PROCESSING,SENT,DELIVERED,FAILED,UNKNOWN,SIMULATED,SKIPPED'],
            'channel' => ['nullable', 'string', 'in:mail,sms,push'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:15,30,50'],
        ]);

        $query = NotificationDelivery::query()
            ->with(['user:id,name', 'notification'])
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($builder, $channel) => $builder->where('channel', $channel))
            ->when($filters['search'] ?? null, function ($builder, $search): void {
                $builder->where(function ($nested) use ($search): void {
                    if (ctype_digit($search)) {
                        $nested->whereKey((int) $search)
                            ->orWhere('user_id', (int) $search);
                    }

                    $nested->orWhere('recipient_hint', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%'));
                });
            });

        $deliveries = $query->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $base = NotificationDelivery::query();

        return view('admin.notifications.deliveries.index', [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'summary' => [
                'total' => (clone $base)->count(),
                'pending' => (clone $base)->whereIn('status', ['PENDING', 'RETRYING'])->count(),
                'processing' => (clone $base)->where('status', 'PROCESSING')->count(),
                'sent' => (clone $base)->whereIn('status', ['SENT', 'DELIVERED'])->count(),
                'attention' => (clone $base)->whereIn('status', ['FAILED', 'UNKNOWN'])->count(),
            ],
        ]);
    }

    public function retry(Request $request, NotificationDelivery $delivery, AuditLogService $audit): RedirectResponse
    {
        abort_unless($delivery->status === 'FAILED', 409, 'Seules les livraisons définitivement échouées peuvent être relancées.');
        abort_if($delivery->attempts >= 5, 409, 'Le nombre maximal de tentatives est atteint.');
        abort_unless($this->isRetryable($delivery), 409, 'Le dernier échec est permanent ou non classé comme relançable.');
        abort_unless(in_array($delivery->channel, ['mail', 'sms', 'push'], true), 409, 'Canal de livraison non relançable.');

        DB::transaction(function () use ($delivery): void {
            $locked = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($locked->status === 'FAILED' && $locked->attempts < 5, 409);
            $locked->forceFill([
                'status' => 'RETRYING',
                'available_at' => now(),
                'queued_at' => null,
                'processing_at' => null,
                'last_error_code' => null,
            ])->saveQuietly();
        });

        $delivery->refresh();
        match ($delivery->channel) {
            'sms' => SendNotificationSms::dispatch($delivery->id)->afterCommit(),
            'push' => SendNotificationPush::dispatch($delivery->id)->afterCommit(),
            default => SendNotificationEmail::dispatch($delivery->id)->afterCommit(),
        };
        $audit->record('notification_delivery_retried', $delivery, $request->user(), [
            'channel' => $delivery->channel,
            'attempts' => $delivery->attempts,
        ], $request);

        return back()->with('success', 'Nouvelle tentative de livraison mise en file.');
    }

    private function isRetryable(NotificationDelivery $delivery): bool
    {
        $error = (string) $delivery->last_error_code;

        if ($delivery->channel === 'push') {
            return $error === 'PUSH_PROVIDER_TEMPORARY_FAILURE';
        }

        if ($delivery->channel === 'sms') {
            return preg_match('/^SMS_PROVIDER_HTTP_(429|5[0-9]{2})$/', $error) === 1;
        }

        return $delivery->channel === 'mail'
            && (str_contains($error, 'TransportException')
                || str_contains($error, 'ConnectionException')
                || str_contains($error, 'Timeout'));
    }

    public function show(NotificationDelivery $delivery): View
    {
        $delivery->load(['user:id,name', 'notification', 'attempts']);

        return view('admin.notifications.deliveries.show', [
            'delivery' => $delivery,
            'retryable' => $this->isRetryable($delivery),
        ]);
    }
}
