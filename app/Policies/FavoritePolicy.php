<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\User;

class FavoritePolicy
{
    public function create(User $user, ProfessionalProfile $professionalProfile): bool
    {
        return $professionalProfile->user_id !== $user->getKey()
            && ProfessionalProfile::query()
                ->publiclyDiscoverable()
                ->whereKey($professionalProfile->getKey())
                ->exists();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->user_id === $user->getKey();
    }
}
