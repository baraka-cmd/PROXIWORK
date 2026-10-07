<?php

declare(strict_types=1);

namespace App\Policies\Address;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('profiles.view');
    }

    public function view(User $user, Address $address): bool
    {
        return $user->getKey() === $address->user_id
            && $user->hasPermissionTo('profiles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('profiles.manage');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->getKey() === $address->user_id
            && $user->hasPermissionTo('profiles.manage');
    }

    public function setDefault(User $user, Address $address): bool
    {
        return $user->getKey() === $address->user_id
            && $user->hasPermissionTo('profiles.manage');
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->getKey() === $address->user_id
            && $user->hasPermissionTo('profiles.manage');
    }
}
