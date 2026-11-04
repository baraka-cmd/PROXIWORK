<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;

class FavoritePolicy
{
    public function create(User $user, ProfessionalProfile|Service $target): bool
    {
        if ($target instanceof ProfessionalProfile) {
            return $target->user_id !== $user->getKey()
                && $target->isPubliclyVisible();
        }

        $professional = $target->professionalProfile;

        return $professional !== null
            && $professional->user_id !== $user->getKey()
            && $target->isRequestableForClient();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->user_id === $user->getKey();
    }
}
