<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProfessionalProfile;
use App\Models\User;

class ProfessionalProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('admin.professionals.view');
    }

    public function view(User $user, ProfessionalProfile $professional): bool
    {
        return $user->hasPermissionTo('admin.professionals.view');
    }

    public function suspend(User $user, ProfessionalProfile $professional): bool
    {
        return $user->hasPermissionTo('admin.professionals.suspend');
    }

    public function activate(User $user, ProfessionalProfile $professional): bool
    {
        return $user->hasPermissionTo('admin.professionals.activate');
    }

    public function review(User $user, ProfessionalProfile $professional): bool
    {
        return $user->hasPermissionTo('admin.professionals.review');
    }

    public function verify(User $user, ProfessionalProfile $professional): bool
    {
        return $user->hasPermissionTo('admin.professionals.verify');
    }

    public function reject(User $user, ProfessionalProfile $professional): bool
    {
        return $user->hasPermissionTo('admin.professionals.reject');
    }
}
