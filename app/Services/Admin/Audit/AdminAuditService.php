<?php

declare(strict_types=1);

namespace App\Services\Admin\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminAuditService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('user:id,name,email');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($query) use ($search): void {
                $query->where('action', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhere('subject_id', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($user) use ($search): void {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        foreach (['user_id', 'action', 'subject_type', 'subject_id', 'ip_address'] as $filter) {
            if (($value = $filters[$filter] ?? null) !== null && $value !== '') {
                $query->where($filter, $value);
            }
        }

        $query->when(
            $filters['created_from'] ?? null,
            fn ($q, $date) => $q->where('created_at', '>=', $date),
        );
        $query->when(
            $filters['created_to'] ?? null,
            fn ($q, $date) => $q->where('created_at', '<=', $date),
        );

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();
    }

    public function show(AuditLog $auditLog): AuditLog
    {
        return $auditLog->load('user:id,name,email');
    }
}
