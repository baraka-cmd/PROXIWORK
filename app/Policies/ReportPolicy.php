<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('reports.manage');
    }

    public function view(User $user, Report $report): bool
    {
        return $user->hasPermissionTo('reports.manage');
    }

    public function manage(User $user, Report $report): bool
    {
        return $user->hasPermissionTo('reports.manage');
    }
}
