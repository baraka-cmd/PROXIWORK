<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('rbac.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('rbac.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('rbac.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('rbac.manage') && ! $role->is_system;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('rbac.manage') && ! $role->is_system;
    }
}
