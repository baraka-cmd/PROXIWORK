<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\SecurityAlert;
use Illuminate\Support\Facades\DB;

class SecurityAlertService
{
    public function observe(AuditLog $event): void
    {
        if ($event->action !== 'auth.login.failed') {
            return;
        }

        $windowStart = now()->subMinutes(5);
        $identifierHash = $event->metadata['identifier_hash'] ?? null;

        $query = AuditLog::query()
            ->where('category', 'authentication')
            ->where('action', 'auth.login.failed')
            ->where('created_at', '>=', $windowStart);

        if ($event->ip_address !== null) {
            $query->where('ip_address', $event->ip_address);
        } elseif (is_string($identifierHash)) {
            $query->where('metadata->identifier_hash', $identifierHash);
        } else {
            return;
        }

        $count = (clone $query)->count();

        if ($count < 10) {
            return;
        }

        $fingerprint = hash('sha256', implode('|', [
            'auth.login.failed',
            $event->ip_address ?? 'no-ip',
            $identifierHash ?? 'no-identifier',
        ]));

        DB::transaction(function () use ($event, $fingerprint, $count, $windowStart): void {
            $alert = SecurityAlert::query()
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($alert === null) {
                SecurityAlert::query()->create([
                    'user_id' => $event->user_id,
                    'source_audit_log_id' => $event->getKey(),
                    'rule_key' => 'auth.failed-login.burst',
                    'fingerprint' => $fingerprint,
                    'severity' => 'high',
                    'status' => 'new',
                    'title' => 'Multiples échecs de connexion',
                    'description' => 'Au moins 10 tentatives de connexion échouées ont été observées sur une fenêtre de cinq minutes.',
                    'context' => ['window_minutes' => 5, 'observed_attempts' => $count],
                    'occurrences' => 1,
                    'first_seen_at' => $windowStart,
                    'last_seen_at' => now(),
                ]);

                return;
            }

            $alert->forceFill([
                'source_audit_log_id' => $event->getKey(),
                'context' => ['window_minutes' => 5, 'observed_attempts' => $count],
                'occurrences' => $alert->occurrences + 1,
                'last_seen_at' => now(),
                'status' => in_array($alert->status, ['resolved', 'false_positive'], true) ? 'new' : $alert->status,
                'resolved_at' => null,
            ])->save();
        }, attempts: 3);
    }

    public function resolve(SecurityAlert $alert, int $actorId, string $status, string $note): SecurityAlert
    {
        abort_unless(in_array($status, ['investigating', 'confirmed', 'false_positive', 'resolved', 'closed'], true), 422);

        $alert->forceFill([
            'status' => $status,
            'assigned_to' => $actorId,
            'resolution_note' => trim($note),
            'resolved_at' => in_array($status, ['resolved', 'closed', 'false_positive'], true) ? now() : null,
        ])->save();

        return $alert->refresh();
    }
}
