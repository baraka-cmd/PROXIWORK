<?php

declare(strict_types=1);

namespace App\Services\Admin\Communication;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CommunicationAudienceService
{
    public function query(array $audience): Builder
    {
        $role = $audience['role'] ?? 'all';

        return User::query()
            ->where('account_status', 'active')
            ->whereNotNull('email_verified_at')
            ->when(
                in_array($role, ['client', 'professional'], true),
                fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role)),
            )
            ->when(
                ($audience['recent_days'] ?? null) !== null,
                fn (Builder $query) => $query->where('created_at', '>=', now()->subDays((int) $audience['recent_days'])),
            );
    }

    public function count(array $audience): int
    {
        return $this->query($audience)->count();
    }
}