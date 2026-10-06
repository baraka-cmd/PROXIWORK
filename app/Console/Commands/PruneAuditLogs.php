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
        $deleted = AuditLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$deleted} audit log(s) supprimé(s).");

        return self::SUCCESS;
    }
}
