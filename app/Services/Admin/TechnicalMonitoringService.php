<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryAttempt;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TechnicalMonitoringService
{
    /**
     * Return operational indicators only. This service never exposes queue
     * payloads, exception traces, credentials, notification bodies or payment
     * metadata to the administration UI.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $application = $this->applicationHealth();

        return [
            'checked_at' => now(),
            'application' => $application,
            'queues' => $application['database'] === 'available' ? $this->queueHealth() : ['available' => false, 'queues' => [], 'pending' => 0, 'reserved' => 0, 'oldest_wait_seconds' => null],
            'failed_jobs' => $application['database'] === 'available' ? $this->failedJobs() : ['available' => false, 'total' => 0, 'recent' => []],
            'notifications' => $application['database'] === 'available' ? $this->notificationHealth() : ['available' => false, 'total' => 0, 'by_status' => [], 'attention' => 0],
            'payments' => $application['database'] === 'available' ? $this->paymentHealth() : ['available' => false, 'total' => 0, 'by_provider' => [], 'attention' => 0],
            'storage' => $this->storageHealth($application['database'] === 'available'),
            'errors' => $this->applicationLogHealth(),
            'incidents' => $application['database'] === 'available' ? $this->incidentHealth() : ['available' => false, 'total' => 0, 'recent' => []],
            'performance' => $this->performanceHealth($application['database_latency_ms'], $application['database'] === 'available'),
        ];
    }

    /** @return array<string, mixed> */
    private function applicationHealth(): array
    {
        $started = microtime(true);

        try {
            DB::select('select 1');

            return [
                'database' => 'available',
                'database_latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ];
        } catch (Throwable) {
            return [
                'database' => 'unavailable',
                'database_latency_ms' => null,
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ];
        }
    }

    /** @return array<string, mixed> */
    private function queueHealth(): array
    {
        if (! Schema::hasTable('jobs')) {
            return ['available' => false, 'queues' => [], 'pending' => 0, 'reserved' => 0, 'oldest_wait_seconds' => null];
        }

        $now = now()->timestamp;
        $queues = DB::table('jobs')
            ->select('queue')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL THEN 1 ELSE 0 END) as pending')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as reserved')
            ->groupBy('queue')
            ->orderBy('queue')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->queue,
                'total' => (int) $row->total,
                'pending' => (int) $row->pending,
                'reserved' => (int) $row->reserved,
            ])
            ->all();

        $oldest = DB::table('jobs')->whereNull('reserved_at')->min('created_at');

        return [
            'available' => true,
            'queues' => $queues,
            'pending' => array_sum(array_column($queues, 'pending')),
            'reserved' => array_sum(array_column($queues, 'reserved')),
            'oldest_wait_seconds' => $oldest === null ? null : max(0, $now - (int) $oldest),
            // A database queue cannot prove that an idle worker process is alive.
            'worker_count' => Schema::hasTable('queue_worker_heartbeats')
                ? DB::table('queue_worker_heartbeats')->where('last_seen_at', '>=', now()->subMinutes(2))->count()
                : null,
            'worker_status' => Schema::hasTable('queue_worker_heartbeats')
                ? (DB::table('queue_worker_heartbeats')->where('last_seen_at', '>=', now()->subMinutes(2))->exists() ? 'active' : 'inactive')
                : 'not_instrumented',
        ];
    }

    /** @return array<string, mixed> */
    private function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['available' => false, 'total' => 0, 'recent' => []];
        }

        $recent = DB::table('failed_jobs')
            ->select(['uuid', 'queue', 'failed_at', 'payload'])
            ->latest('failed_at')
            ->limit(10)
            ->get()
            ->map(function (object $job): array {
                $payload = json_decode((string) $job->payload, true);

                return [
                    'uuid' => (string) $job->uuid,
                    'queue' => (string) $job->queue,
                    'failed_at' => $job->failed_at,
                    'job_name' => is_array($payload) && is_string($payload['displayName'] ?? null)
                        ? class_basename($payload['displayName'])
                        : 'Tâche inconnue',
                ];
            })
            ->all();

        return [
            'available' => true,
            'total' => DB::table('failed_jobs')->count(),
            'recent' => $recent,
        ];
    }

    /** @return array<string, mixed> */
    private function notificationHealth(): array
    {
        if (! Schema::hasTable('notification_deliveries')) {
            return ['available' => false, 'total' => 0, 'by_status' => [], 'attention' => 0];
        }

        $statuses = NotificationDelivery::query()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();

        return [
            'available' => true,
            'total' => array_sum($statuses),
            'by_status' => $statuses,
            'attention' => (int) (($statuses['FAILED'] ?? 0) + ($statuses['UNKNOWN'] ?? 0)),
            'attempts_total' => Schema::hasTable('notification_delivery_attempts')
                ? NotificationDeliveryAttempt::query()->count()
                : null,
            'recent_failures' => Schema::hasTable('notification_delivery_attempts')
                ? NotificationDeliveryAttempt::query()
                    ->whereIn('status', ['FAILED', 'UNKNOWN'])
                    ->latest('started_at')
                    ->limit(5)
                    ->get(['notification_delivery_id', 'attempt_number', 'channel', 'status', 'error_code', 'started_at'])
                    ->map(fn (NotificationDeliveryAttempt $attempt): array => [
                        'delivery_id' => (int) $attempt->notification_delivery_id,
                        'attempt_number' => (int) $attempt->attempt_number,
                        'channel' => (string) $attempt->channel,
                        'status' => (string) $attempt->status,
                        'error_code' => $attempt->error_code,
                        'started_at' => $attempt->started_at,
                    ])
                    ->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function paymentHealth(): array
    {
        if (! Schema::hasTable('payments')) {
            return ['available' => false, 'total' => 0, 'by_provider' => [], 'attention' => 0];
        }

        $rows = Payment::query()
            ->select(['provider', 'status'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('provider', 'status')
            ->orderBy('provider')
            ->get();

        $providers = [];

        foreach ($rows as $row) {
            $provider = $row->provider instanceof \BackedEnum ? $row->provider->value : (string) $row->provider;
            $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
            $total = (int) $row->total;
            $providers[$provider][$status] = $total;

        }

        $unknownTransactions = Schema::hasTable('payment_transactions')
            ? PaymentTransaction::query()->where('status', 'unknown')->count()
            : null;
        $stalePayments = Payment::query()
            ->whereIn('status', ['initiated', 'pending', 'processing'])
            ->where('created_at', '<=', now()->subMinutes(15))
            ->count();

        return [
            'available' => true,
            'total' => (int) Payment::query()->count(),
            'by_provider' => $providers,
            'attention' => $stalePayments + ($unknownTransactions ?? 0),
            'stale_payments' => $stalePayments,
            'unknown_transactions' => $unknownTransactions,
            'event_total' => Schema::hasTable('payment_events') ? PaymentEvent::query()->count() : null,
            'recent_events' => Schema::hasTable('payment_events')
                ? PaymentEvent::query()
                    ->latest('occurred_at')
                    ->limit(10)
                    ->get(['id', 'payment_id', 'event_type', 'from_status', 'to_status', 'source', 'occurred_at'])
                    ->map(fn (PaymentEvent $event): array => [
                        'payment_id' => (int) $event->payment_id,
                        'event_type' => (string) $event->event_type,
                        'from_status' => $event->from_status,
                        'to_status' => (string) $event->to_status,
                        'source' => (string) $event->source,
                        'occurred_at' => $event->occurred_at,
                    ])
                    ->all()
                : [],
            // Provider credentials are intentionally never inspected or displayed here.
            'provider_connectivity' => 'not_checked',
        ];
    }

    /** @return array<string, mixed> */
    private function storageHealth(bool $databaseAvailable): array
    {
        $disks = [];

        foreach (['local', 'public'] as $name) {
            try {
                $disk = Storage::disk($name);
                $root = $disk->path('');
                $total = is_dir($root) ? @disk_total_space($root) : false;
                $free = is_dir($root) ? @disk_free_space($root) : false;

                $disks[$name] = [
                    'reachable' => is_dir($root),
                    'capacity_bytes' => $total === false ? null : (int) $total,
                    'free_bytes' => $free === false ? null : (int) $free,
                    'capacity_supported' => $total !== false && $free !== false,
                ];
            } catch (Throwable) {
                $disks[$name] = [
                    'reachable' => false,
                    'capacity_bytes' => null,
                    'free_bytes' => null,
                    'capacity_supported' => false,
                ];
            }
        }

        $integrity = ['available' => false, 'latest' => null];

        if ($databaseAvailable && Schema::hasTable('storage_integrity_runs')) {
            $latest = DB::table('storage_integrity_runs')
                ->latest('id')
                ->first(['status', 'records_checked', 'missing_files', 'check_errors', 'completed_at']);

            $integrity = [
                'available' => true,
                'latest' => $latest === null ? null : [
                    'status' => (string) $latest->status,
                    'records_checked' => (int) $latest->records_checked,
                    'missing_files' => (int) $latest->missing_files,
                    'check_errors' => (int) $latest->check_errors,
                    'completed_at' => $latest->completed_at,
                ],
            ];
        }

        return ['disks' => $disks, 'integrity' => $integrity];
    }

    /** @return array<string, mixed> */
    private function applicationLogHealth(): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $latest = $files[0] ?? null;

        return [
            'available' => $latest !== null && is_readable($latest),
            'file_name' => $latest === null ? null : basename($latest),
            'size_bytes' => $latest === null ? null : (int) (filesize($latest) ?: 0),
            'last_written_at' => $latest === null ? null : date(DATE_ATOM, (int) filemtime($latest)),
            // Raw logs are not parsed or rendered here: they may contain sensitive context.
            'incident_aggregation' => 'application_incidents',
        ];
    }

    /** @return array<string, mixed> */
    private function incidentHealth(): array
    {
        if (! Schema::hasTable('application_incidents')) {
            return ['available' => false, 'total' => 0, 'recent' => []];
        }

        return [
            'available' => true,
            'total' => DB::table('application_incidents')->count(),
            'recent' => DB::table('application_incidents')
                ->orderByDesc('last_seen_at')
                ->limit(10)
                ->get(['exception_type', 'location_hint', 'occurrence_count', 'first_seen_at', 'last_seen_at'])
                ->map(fn (object $incident): array => [
                    'exception_type' => (string) $incident->exception_type,
                    'location_hint' => (string) $incident->location_hint,
                    'occurrence_count' => (int) $incident->occurrence_count,
                    'first_seen_at' => $incident->first_seen_at,
                    'last_seen_at' => $incident->last_seen_at,
                ])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function performanceHealth(?int $databaseLatencyMs, bool $databaseAvailable): array
    {
        $snapshots = $databaseAvailable && Schema::hasTable('http_performance_snapshots')
            ? DB::table('http_performance_snapshots')
                ->where('bucket_at', '>=', now()->subHour())
                ->orderBy('bucket_at')
                ->get()
            : collect();

        $requests = (int) $snapshots->sum('request_count');
        $errors = (int) $snapshots->sum('error_count');
        $durationTotal = (int) $snapshots->sum('duration_total_ms');
        $latest = $snapshots->last();

        return [
            'database_probe_ms' => $databaseLatencyMs,
            'request_count_last_hour' => $requests,
            'http_error_rate' => $requests > 0 ? round(($errors / $requests) * 100, 2) : null,
            'average_response_ms' => $requests > 0 ? (int) round($durationTotal / $requests) : null,
            'p95_bucket_ms' => $latest?->p95_bucket_ms,
            'availability_history' => null,
            'message' => 'Les métriques HTTP sont agrégées par minute ; la disponibilité externe nécessite un contrôle indépendant.',
        ];
    }
}
