<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\User;

class FavoritePolicy
{
    public function create(User $user, ProfessionalProfile $professionalProfile): bool
    {
        return $professionalProfile->user_id !== $user->getKey()
            && $professionalProfile->status === ProfessionalProfile::STATUS_ACTIVE
            && $professionalProfile->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
            && $professionalProfile->verification_status === ProfessionalVerificationStatus::VERIFIED
            && $professionalProfile->user()
                ->where('account_status', UserAccountStatus::ACTIVE->value)
                ->whereNotNull('email_verified_at')
                ->exists();
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->user_id === $user->getKey();
    }
}
