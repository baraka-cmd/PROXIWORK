<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('audit:prune')->dailyAt('03:30');
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('quotations:expire')->hourly()->withoutOverlapping();
// Durable notification deliveries are dispatched independently of business requests.
Schedule::command('notifications:dispatch-pending')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
