<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('admin.users.view');
    }

    public function view(User $user, User $subject): bool
    {
        return $user->hasPermissionTo('admin.users.view');
    }

    public function viewSensitive(User $user): bool
    {
        return $user->hasPermissionTo('admin.users.view_sensitive');
    }

    public function update(User $user, User $subject): bool
    {
        return $user->hasPermissionTo('admin.users.update')
            && $user->getKey() !== $subject->getKey()
            && !$subject->hasRole('admin');
    }

    public function suspend(User $user, User $subject): bool
    {
        return $user->hasPermissionTo('admin.users.suspend')
            && $user->getKey() !== $subject->getKey()
            && !$subject->hasRole('admin')
            && $subject->account_status === UserAccountStatus::ACTIVE;
    }

    public function activate(User $user, User $subject): bool
    {
        return $user->hasPermissionTo('admin.users.activate')
            && $user->getKey() !== $subject->getKey()
            && !$subject->hasRole('admin')
            && $subject->account_status === UserAccountStatus::SUSPENDED;
    }
}
