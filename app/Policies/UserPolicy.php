<?php

declare(strict_types=1);

namespace App\Policies;

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

    public function suspend(User $user, User $subject): bool
    {
        return $user->hasPermissionTo('admin.users.suspend')
            && $user->getKey() !== $subject->getKey();
    }

    public function activate(User $user, User $subject): bool
    {
        return $user->hasPermissionTo('admin.users.activate');
    }
}
