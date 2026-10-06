<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->client_id === $user->getKey()
            || $order->professional?->user_id === $user->getKey();
    }

    public function create(User $user, Order $order): bool
    {
        return $user->hasRole('client')
            && $order->client_id === $user->getKey();
    }
}
