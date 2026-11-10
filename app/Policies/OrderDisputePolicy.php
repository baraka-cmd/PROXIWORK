<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderDispute;
use App\Models\User;

class OrderDisputePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('disputes.view'); }
    public function view(User $user, OrderDispute $dispute): bool
    {
        return $user->hasPermissionTo('disputes.view')
            || (int) $dispute->client_id === (int) $user->getKey()
            || (int) $dispute->professional()->value('user_id') === (int) $user->getKey();
    }
    public function manage(User $user, OrderDispute $dispute): bool { return $user->hasPermissionTo('disputes.manage'); }
    public function decide(User $user, OrderDispute $dispute): bool { return $user->hasPermissionTo('disputes.decide'); }
    public function message(User $user, OrderDispute $dispute): bool
    {
        return $user->hasPermissionTo('disputes.manage')
            || (int) $dispute->client_id === (int) $user->getKey()
            || (int) $dispute->professional()->value('user_id') === (int) $user->getKey();
    }
}
