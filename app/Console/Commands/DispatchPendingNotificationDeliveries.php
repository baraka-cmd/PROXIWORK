<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Notifications\SendNotificationEmail;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchPendingNotificationDeliveries extends Command
{
    protected $signature = 'notifications:dispatch-pending {--limit=100 : Maximum delivery jobs to dispatch per run}';

    protected $description = 'Dispatch due notification delivery records to the queue and reconcile stale attempts.';

    public function handle(): int
    {
        $now = now();

        $stale = NotificationDelivery::query()
            ->where('channel', 'mail')
            ->where('status', 'PROCESSING')
            ->where('processing_at', '<=', $now->copy()->subMinutes(15))
            ->update([
                'status' => 'UNKNOWN',
                'processing_at' => null,
                'last_error_code' => 'StaleProcessingRequiresReconciliation',
                'updated_at' => $now,
            ]);

        if ($stale > 0) {
            Log::warning('PROXIWORK notification deliveries need reconciliation.', [
                'count' => $stale,
            ]);
        }

        $limit = min(max((int) $this->option('limit'), 1), 500);
        $candidateIds = NotificationDelivery::query()
            ->where('channel', 'mail')
            ->whereIn('status', ['PENDING', 'RETRYING'])
            ->where(function ($query) use ($now): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('queued_at')
                    ->orWhere('queued_at', '<=', $now->copy()->subMinutes(10));
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $dispatched = 0;

        foreach ($candidateIds as $id) {
            $claimed = DB::transaction(function () use ($id): bool {
                $delivery = NotificationDelivery::query()->lockForUpdate()->find($id);

                if (
                    $delivery === null
                    || ! in_array($delivery->status, ['PENDING', 'RETRYING'], true)
                    || ($delivery->available_at !== null && $delivery->available_at->isFuture())
                    || ($delivery->queued_at !== null && $delivery->queued_at->gt(now()->subMinutes(10)))
                ) {
                    return false;
                }

                $delivery->update(['queued_at' => now()]);

                return true;
            });

            if (! $claimed) {
                continue;
            }

            try {
                SendNotificationEmail::dispatch((int) $id);
                $dispatched++;
            } catch (Throwable $exception) {
                NotificationDelivery::query()->whereKey($id)->update([
                    'queued_at' => null,
                    'updated_at' => now(),
                ]);

                Log::error('PROXIWORK could not dispatch a notification delivery.', [
                    'delivery_id' => $id,
                    'exception' => $exception::class,
                ]);
            }
        }

        $this->info("{$dispatched} livraison(s) de notification mise(s) en file d’attente.");

        return self::SUCCESS;
    }
}
