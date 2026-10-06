<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class FavoriteService
{
    public function add(User $user, ProfessionalProfile $professionalProfile): array
    {
        try {
            $favorite = DB::transaction(
                fn (): Favorite => Favorite::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                    'professional_profile_id' => $professionalProfile->getKey(),
                ])
            );

            return [$favorite->load(['professionalProfile.user.profile']), $favorite->wasRecentlyCreated];
        } catch (UniqueConstraintViolationException) {
            $favorite = Favorite::query()
                ->where('user_id', $user->getKey())
                ->where('professional_profile_id', $professionalProfile->getKey())
                ->firstOrFail();

            return [$favorite->load(['professionalProfile.user.profile']), false];
        }
    }

    public function remove(User $user, ProfessionalProfile $professionalProfile): void
    {
        Favorite::query()
            ->where('user_id', $user->getKey())
            ->where('professional_profile_id', $professionalProfile->getKey())
            ->delete();
    }
}
