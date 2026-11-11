<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('audit:prune')->dailyAt('03:30');
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('quotations:expire')->hourly()->withoutOverlapping();
// Durable notification deliveries are dispatched independently of business requests.
Schedule::command('notifications:dispatch-pending')->everyMinute()->withoutOverlapping();
Schedule::command('communication:dispatch-scheduled')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $bucketAt = now()->subMinute()->startOfMinute();
    $prefix = 'http-performance:'.$bucketAt->format('YmdHi');
    $requests = (int) Cache::pull($prefix.':requests', 0);

    if ($requests < 1) {
        return;
    }

    $buckets = [
        'le_100' => (int) Cache::pull($prefix.':le_100', 0),
        'le_250' => (int) Cache::pull($prefix.':le_250', 0),
        'le_500' => (int) Cache::pull($prefix.':le_500', 0),
        'le_1000' => (int) Cache::pull($prefix.':le_1000', 0),
        'gt_1000' => (int) Cache::pull($prefix.':gt_1000', 0),
    ];
    $target = (int) ceil($requests * 0.95);
    $cumulative = 0;
    $p95Bucket = 2000;

    foreach (['le_100' => 100, 'le_250' => 250, 'le_500' => 500, 'le_1000' => 1000, 'gt_1000' => 2000] as $key => $upperBound) {
        $cumulative += $buckets[$key];
        if ($cumulative >= $target) {
            $p95Bucket = $upperBound;
            break;
        }
    }

    DB::table('http_performance_snapshots')->updateOrInsert(
        ['bucket_at' => $bucketAt],
        [
            'request_count' => $requests,
            'error_count' => (int) Cache::pull($prefix.':errors_5xx', 0),
            'duration_total_ms' => (int) Cache::pull($prefix.':duration_total_ms', 0),
            'p95_bucket_ms' => $p95Bucket,
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );
})->everyMinute()->name('http-performance-snapshot')->withoutOverlapping();


Schedule::call(function (): void {
    if (Schema::hasTable('http_performance_snapshots')) {
        DB::table('http_performance_snapshots')
            ->where('bucket_at', '<', now()->subDays(30))
            ->delete();
    }

    if (Schema::hasTable('queue_worker_heartbeats')) {
        DB::table('queue_worker_heartbeats')
            ->where('last_seen_at', '<', now()->subDays(7))
            ->delete();
    }

    if (Schema::hasTable('application_incidents')) {
        DB::table('application_incidents')
            ->where('last_seen_at', '<', now()->subDays(90))
            ->delete();
    }
})->dailyAt('03:50')->name('monitoring-telemetry-retention')->withoutOverlapping();
