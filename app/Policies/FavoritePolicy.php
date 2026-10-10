<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserAccountStatus;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;

class FavoritePolicy
{
    public function create(User $user, ProfessionalProfile $professionalProfile): bool
    {
        return $professionalProfile->user_id !== $user->getKey()
            && $professionalProfile->status === ProfessionalProfile::STATUS_ACTIVE
            && $professionalProfile->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
            && $professionalProfile->user()
                ->where('account_status', UserAccountStatus::ACTIVE->value)
                ->exists()
            && Service::query()
                ->publiclyVisible()
                ->where('professional_profile_id', $professionalProfile->getKey())
                ->exists();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->user_id === $user->getKey();
    }
}
