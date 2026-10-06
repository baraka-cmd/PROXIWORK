<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReviewResponse;
use App\Models\User;

class ReviewResponsePolicy
{
    public function view(User $user, ReviewResponse $response): bool
    {
        return $response->professional?->user_id === $user->getKey()
            || $response->review?->client_id === $user->getKey();
    }
}
