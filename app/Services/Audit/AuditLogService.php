<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        string $action,
        ?Model $subject = null,
        ?Authenticatable $user = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        $metadata = $this->sanitizeMetadata($metadata);

        return AuditLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $this->truncateUserAgent($request?->userAgent()),
            'metadata' => $metadata,
        ]);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'authorization',
            'secret',
        ];

        foreach ($sensitiveKeys as $key) {
            unset($metadata[$key]);
        }

        return $metadata;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        return $userAgent === null ? null : mb_substr($userAgent, 0, 2000);
    }
}
