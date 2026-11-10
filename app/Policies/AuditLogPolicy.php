<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('audit.view')
            || $user->hasPermissionTo('security.events.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if (in_array($auditLog->category, ['authentication', 'security'], true)) {
            return $user->hasPermissionTo('security.events.view');
        }

        return $user->hasPermissionTo('audit.view');
    }
}
