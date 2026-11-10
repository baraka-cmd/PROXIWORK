<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days= : Override the configured retention period}';

    protected $description = 'Remove audit logs older than the configured retention period.';

    public function handle(): int
    {
        $days = max((int) ($this->option('days') ?: config('audit.retention_days', 180)), 1);
        $cutoff = now()->subDays($days);
        $deleted = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $deletedSessions = DB::table('security_sessions')
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($query) use ($cutoff): void {
                    $query->whereNotNull('revoked_at')->where('revoked_at', '<', $cutoff);
                })->orWhere(function ($query) use ($cutoff): void {
                    $query->whereNotNull('expires_at')->where('expires_at', '<', $cutoff);
                });
            })
            ->delete();

        $deletedAlerts = DB::table('security_alerts')
            ->whereIn('status', ['resolved', 'false_positive', 'closed'])
            ->where('last_seen_at', '<', $cutoff)
            ->delete();

        $deletedInterventions = DB::table('sensitive_interventions')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("{$deleted} audit log(s), {$deletedSessions} session(s), {$deletedAlerts} alerte(s) résolue(s) et {$deletedInterventions} intervention(s) supprimé(s).");

        return self::SUCCESS;
    }
}
