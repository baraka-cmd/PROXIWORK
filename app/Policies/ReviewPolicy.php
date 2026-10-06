<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function view(User $user, Review $review): bool
    {
        return $review->client_id === $user->getKey()
            || $review->professional?->user_id === $user->getKey();
    }

    public function respond(User $user, Review $review): bool
    {
        return $review->professional?->user_id === $user->getKey();
    }
}
