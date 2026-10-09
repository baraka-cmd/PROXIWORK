<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Withdrawal;

class WithdrawalPolicy
{
    public function view(User $user, Withdrawal $withdrawal): bool
    {
        return $withdrawal->professional?->user_id === $user->getKey()
            && $user->hasRole('professional');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('professional');
    }
}
