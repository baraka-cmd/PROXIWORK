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
        $rule = match ($event->action) {
            'auth.login.failed' => [
                'threshold' => 10,
                'rule_key' => 'auth.failed-login.burst',
                'title' => 'Multiples échecs de connexion',
                'description' => 'Au moins 10 tentatives de connexion échouées ont été observées sur une fenêtre de cinq minutes.',
                'severity' => 'high',
            ],
            'security.authorization.denied' => [
                'threshold' => 20,
                'rule_key' => 'security.forbidden-burst',
                'title' => 'Multiples accès refusés',
                'description' => 'Au moins 20 accès refusés ont été observés depuis le même contexte réseau sur cinq minutes.',
                'severity' => 'medium',
            ],
            default => null,
        };

        if ($rule === null) {
            return;
        }

        $windowStart = now()->subMinutes(5);
        $identifierHash = $event->identifier_hash;

        $query = AuditLog::query()
            ->where('category', $event->category)
            ->where('action', $event->action)
            ->where('created_at', '>=', $windowStart);

        if ($event->ip_address !== null) {
            $query->where('ip_address', $event->ip_address);
        } elseif (is_string($identifierHash)) {
            $query->where('identifier_hash', $identifierHash);
        } else {
            return;
        }

        $count = (clone $query)->count();

        if ($count < $rule['threshold']) {
            return;
        }

        $fingerprint = hash('sha256', implode('|', [
            $event->action,
            $event->ip_address ?? 'no-ip',
            $event->ip_address === null ? ($identifierHash ?? 'no-identifier') : 'network',
        ]));

        DB::transaction(function () use ($event, $fingerprint, $count, $windowStart, $rule): void {
            $alert = SecurityAlert::query()
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($alert === null) {
                SecurityAlert::query()->create([
                    'user_id' => $event->user_id,
                    'source_audit_log_id' => $event->getKey(),
                    'rule_key' => $rule['rule_key'],
                    'fingerprint' => $fingerprint,
                    'severity' => $rule['severity'],
                    'status' => 'new',
                    'title' => $rule['title'],
                    'description' => $rule['description'],
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
