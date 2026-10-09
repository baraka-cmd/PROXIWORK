<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $service->professionalProfile->user_id === $user->getKey()
            && $user->hasPermissionTo('services.view');
    }

    public function adminView(User $user, Service $service): bool
    {
        return $user->hasPermissionTo('services.view');
    }

    public function adminManage(User $user, Service $service): bool
    {
        return $user->hasPermissionTo('services.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('services.manage');
    }

    public function update(User $user, Service $service): bool
    {
        return $service->professionalProfile->user_id === $user->getKey()
            && $user->hasPermissionTo('services.manage');
    }

    public function delete(User $user, Service $service): bool
    {
        return $service->professionalProfile->user_id === $user->getKey()
            && $user->hasPermissionTo('services.manage');
    }
}
