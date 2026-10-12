<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
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
                && $target->status === ProfessionalProfile::STATUS_ACTIVE
                && $target->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
                && $target->verification_status === ProfessionalVerificationStatus::VERIFIED
                && $target->user?->isActive() === true;
        }

        $professional = $target->professionalProfile;
        $category = $target->category;

        return $target->status === ServiceStatus::PUBLISHED
            && $target->published_at !== null
            && $category?->status === CategoryStatus::ACTIVE
            && $professional !== null
            && $professional->user_id !== $user->getKey()
            && $professional->status === ProfessionalProfile::STATUS_ACTIVE
            && $professional->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
            && $professional->verification_status === ProfessionalVerificationStatus::VERIFIED
            && $professional->user?->isActive() === true;
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $favorite->user_id === $user->getKey();
    }
}
