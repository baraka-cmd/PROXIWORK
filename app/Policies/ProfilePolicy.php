<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

class ProfilePolicy
{
    public function view(User $user, Profile $profile): bool
    {
        return $user->getKey() === $profile->user_id
            && $user->hasPermissionTo('profiles.view');
    }

    public function update(User $user, Profile $profile): bool
    {
        return $user->getKey() === $profile->user_id
            && $user->hasPermissionTo('profiles.manage');
    }
}
