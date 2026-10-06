<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;

class SkillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('skills.view');
    }

    public function view(User $user, Skill $skill): bool
    {
        return $user->hasPermissionTo('skills.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('skills.manage');
    }

    public function update(User $user, Skill $skill): bool
    {
        return $user->hasPermissionTo('skills.manage');
    }

    public function delete(User $user, Skill $skill): bool
    {
        return $user->hasPermissionTo('skills.manage');
    }
}
