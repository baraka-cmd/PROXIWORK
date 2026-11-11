<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordHttpPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if (app()->environment('production') && $request->is('up') === false) {
            try {
                $bucket = now()->startOfMinute()->format('YmdHi');
                $prefix = 'http-performance:'.$bucket;
                $this->increment($prefix.':requests');
                $this->increment($prefix.':duration_total_ms', $durationMs);

                if ($response->getStatusCode() >= 500) {
                    $this->increment($prefix.':errors_5xx');
                }

                $latencyBucket = match (true) {
                    $durationMs <= 100 => 'le_100',
                    $durationMs <= 250 => 'le_250',
                    $durationMs <= 500 => 'le_500',
                    $durationMs <= 1000 => 'le_1000',
                    default => 'gt_1000',
                };

                $this->increment($prefix.':'.$latencyBucket);
            } catch (Throwable) {
                // Metrics are best-effort and must never change the HTTP response.
            }
        }

        return $response;
    }

    private function increment(string $key, int $amount = 1): void
    {
        Cache::add($key, 0, now()->addMinutes(3));
        Cache::increment($key, $amount);
    }
}
