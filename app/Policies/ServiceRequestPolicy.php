<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function adminView(User $user, ServiceRequest $request): bool
    {
        return $user->hasPermissionTo('requests.view');
    }

    public function adminManage(User $user, ServiceRequest $request): bool
    {
        return $user->hasPermissionTo('requests.manage');
    }

    public function viewAnyClient(User $user): bool
    {
        return $user->hasRole('client');
    }

    public function viewAnyProfessional(User $user): bool
    {
        return $user->hasRole('professional');
    }

    public function view(User $user, ServiceRequest $request): bool
    {
        return $request->client_id === $user->getKey()
            || $request->professional?->user_id === $user->getKey();
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        return $request->client_id === $user->getKey()
            && $request->status->value === 'draft';
    }

    public function submit(User $user, ServiceRequest $request): bool
    {
        return $request->client_id === $user->getKey()
            && $request->status->value === 'draft';
    }

    public function cancel(User $user, ServiceRequest $request): bool
    {
        return $request->client_id === $user->getKey()
            && in_array($request->status->value, ['draft', 'requested'], true);
    }

    public function reject(User $user, ServiceRequest $request): bool
    {
        return $request->professional?->user_id === $user->getKey()
            && $request->status->value === 'requested';
    }
}
