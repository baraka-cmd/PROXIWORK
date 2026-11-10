<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\SensitiveIntervention;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token',
        'access_token', 'refresh_token', 'authorization', 'secret', 'cookie',
        'session_id', 'identifier_ciphertext', 'destination', 'phone',
        'email', 'address', 'otp', 'signature', 'private_key',
    ];

    private const CHANGE_ALLOWLIST = [
        'status', 'account_status', 'verification_status', 'role', 'permission',
        'visibility', 'is_active', 'amount', 'currency', 'commission_rate',
        'fee_amount', 'refund_status', 'withdrawal_status',
    ];

    public function __construct(
        private readonly SecurityAlertService $securityAlerts,
    ) {}

    public function record(
        string $action,
        ?Model $subject = null,
        ?Authenticatable $user = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        return $this->recordEvent($action, $subject, $user, $metadata, $request);
    }

    public function recordAuthenticationFailure(
        string $identifier,
        Request $request,
        ?Model $subject = null,
        string $failureCode = 'invalid_credentials',
    ): AuditLog {
        $identifierHash = hash_hmac(
            'sha256',
            mb_strtolower(trim($identifier)),
            (string) config('app.key'),
        );

        return $this->recordEvent(
            'auth.login.failed',
            $subject,
            null,
            ['identifier_hash' => $identifierHash, 'failure_code' => $failureCode],
            $request,
            category: 'authentication',
            outcome: 'failure',
            severity: 'low',
        );
    }

    public function recordEvent(
        string $action,
        ?Model $subject = null,
        ?Authenticatable $user = null,
        array $metadata = [],
        ?Request $request = null,
        ?string $category = null,
        string $outcome = 'success',
        string $severity = 'info',
        ?string $reason = null,
        ?array $changes = null,
        ?string $correlationId = null,
    ): AuditLog {
        $category ??= $this->categoryFor($action);
        $correlationId = $correlationId && Str::isUuid($correlationId)
            ? $correlationId
            : (string) Str::uuid();

        $safeMetadata = $this->sanitizeArray($metadata);
        $safeChanges = $this->sanitizeChanges($changes ?? $this->changesFromMetadata($metadata));
        $safeReason = $reason === null ? null : mb_substr(trim($reason), 0, 1000);

        $event = AuditLog::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'action' => mb_substr($action, 0, 100),
            'category' => $category,
            'outcome' => in_array($outcome, ['success', 'failure', 'denied', 'error'], true) ? $outcome : 'success',
            'severity' => in_array($severity, ['info', 'low', 'medium', 'high', 'critical'], true) ? $severity : 'info',
            'correlation_id' => $correlationId,
            'reason' => $safeReason,
            'changes' => $safeChanges,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 1000),
            'metadata' => $safeMetadata,
        ]);

        if (str_starts_with($action, 'admin.') && ! str_starts_with($action, 'admin.auth.') && ! str_ends_with($action, '.viewed')) {
            $this->recordInterventionProjection($event, $subject, $user, $action, $metadata, $safeChanges, $safeReason);
        }

        if (($category === 'authentication' && $outcome === 'failure') || ($category === 'security' && $outcome === 'denied')) {
            $this->securityAlerts->observe($event);
        }

        return $event;
    }

    private function recordInterventionProjection(
        AuditLog $event,
        ?Model $subject,
        ?Authenticatable $actor,
        string $action,
        array $metadata,
        ?array $changes,
        ?string $reason,
    ): void {
        $segments = explode('.', $action);
        $domain = $segments[1] ?? 'administration';
        $reasonText = $reason
            ?? ($metadata['reason'] ?? $metadata['note'] ?? $metadata['review_note'] ?? null);

        $amount = $subject?->getAttribute('amount');
        $currency = $subject?->getAttribute('currency');

        SensitiveIntervention::query()->create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'audit_log_id' => $event->getKey(),
            'domain' => mb_substr($domain, 0, 60),
            'action' => mb_substr($action, 0, 120),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'result' => 'succeeded',
            'reason_hash' => is_string($reasonText) && trim($reasonText) !== ''
                ? hash('sha256', trim($reasonText))
                : null,
            'reason' => is_string($reasonText) ? mb_substr(trim($reasonText), 0, 1000) : null,
            'amount' => is_numeric($amount) ? (string) $amount : null,
            'currency' => is_string($currency) && preg_match('/^[A-Za-z]{3}$/', $currency)
                ? strtoupper($currency)
                : null,
            'changes' => $changes,
            'correlation_id' => $event->correlation_id,
            'created_at' => $event->created_at,
        ]);
    }

    private function categoryFor(string $action): string
    {
        if (str_starts_with($action, 'auth.') || str_starts_with($action, 'admin.auth.') || in_array($action, ['login', 'logout', 'password_changed', 'password_reset'], true)) {
            return 'authentication';
        }

        if (str_starts_with($action, 'security.')) {
            return 'security';
        }

        if (str_starts_with($action, 'admin.')) {
            return 'administrative';
        }

        return 'general';
    }

    private function changesFromMetadata(array $metadata): ?array
    {
        $changes = [];

        foreach (['previous_status' => 'status', 'new_status' => 'status', 'old_status' => 'status', 'status' => 'status'] as $source => $target) {
            if (array_key_exists($source, $metadata)) {
                $changes[$source === 'previous_status' || $source === 'old_status' ? 'before' : 'after'][$target] = $metadata[$source];
            }
        }

        return $changes === [] ? null : $changes;
    }

    private function sanitizeChanges(?array $changes): ?array
    {
        if ($changes === null) {
            return null;
        }

        $safe = [];

        foreach (['before', 'after'] as $side) {
            foreach (($changes[$side] ?? []) as $key => $value) {
                if (in_array((string) $key, self::CHANGE_ALLOWLIST, true) && is_scalar($value)) {
                    $safe[$side][$key] = is_string($value) ? mb_substr($value, 0, 160) : $value;
                }
            }
        }

        return $safe === [] ? null : $safe;
    }

    private function sanitizeArray(array $data, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }

        $safe = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($normalizedKey, $sensitiveKey)) {
                    continue 2;
                }
            }

            if (is_array($value)) {
                $safe[$key] = $this->sanitizeArray($value, $depth + 1);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 2000) : $value;
            }
        }

        return $safe;
    }
}
