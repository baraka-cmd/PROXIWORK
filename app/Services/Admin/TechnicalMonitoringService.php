<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'storage' => $this->storageHealth(),
            'errors' => $this->applicationLogHealth(),
            'performance' => $this->performanceHealth($application['database_latency_ms']),
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
            Log::warning('Technical monitoring: database health check failed.');

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
            'worker_status' => 'not_instrumented',
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
        $attention = 0;

        foreach ($rows as $row) {
            $provider = (string) $row->provider;
            $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
            $total = (int) $row->total;
            $providers[$provider][$status] = $total;

            if (in_array(strtoupper($status), ['UNKNOWN', 'PROCESSING', 'INITIATED'], true)) {
                $attention += $total;
            }
        }

        return [
            'available' => true,
            'total' => (int) Payment::query()->count(),
            'by_provider' => $providers,
            'attention' => $attention,
            'unknown_transactions' => Schema::hasTable('payment_transactions')
                ? PaymentTransaction::query()->where('status', 'UNKNOWN')->count()
                : null,
            // Provider credentials are intentionally never inspected or displayed here.
            'provider_connectivity' => 'not_checked',
        ];
    }

    /** @return array<string, mixed> */
    private function storageHealth(): array
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

        return ['disks' => $disks];
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
            'incident_aggregation' => 'not_instrumented',
        ];
    }

    /** @return array<string, mixed> */
    private function performanceHealth(?int $databaseLatencyMs): array
    {
        return [
            'database_probe_ms' => $databaseLatencyMs,
            'http_percentiles' => null,
            'http_error_rate' => null,
            'availability_history' => null,
            'message' => 'Les mesures HTTP historiques nécessitent une collecte dédiée et un contrôle externe.',
        ];
    }
}
