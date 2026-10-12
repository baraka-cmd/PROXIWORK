<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class FavoriteService
{
    /**
     * @return array{0: Favorite, 1: bool}
     */
    public function add(User $user, ProfessionalProfile $professionalProfile): array
    {
        return $this->addTarget(
            $user,
            ['professional_profile_id' => $professionalProfile->getKey(), 'service_id' => null],
            ['professionalProfile.user.profile']
        );
    }

    /**
     * @return array{0: Favorite, 1: bool}
     */
    public function addService(User $user, Service $service): array
    {
        return $this->addTarget(
            $user,
            ['professional_profile_id' => null, 'service_id' => $service->getKey()],
            ['service.category', 'service.professionalProfile.user.profile', 'service.images']
        );
    }

    /**
     * @param array{professional_profile_id: int|null, service_id: int|null} $target
     * @param array<int, string> $relations
     * @return array{0: Favorite, 1: bool}
     */
    private function addTarget(User $user, array $target, array $relations): array
    {
        $attributes = ['user_id' => $user->getKey(), ...$target];

        try {
            $favorite = DB::transaction(
                fn (): Favorite => Favorite::query()->firstOrCreate($attributes)
            );

            return [$favorite->load($relations), $favorite->wasRecentlyCreated];
        } catch (UniqueConstraintViolationException) {
            $targetColumn = $target['professional_profile_id'] !== null
                ? 'professional_profile_id'
                : 'service_id';
            $targetId = $target[$targetColumn];

            $favorite = Favorite::query()
                ->where('user_id', $user->getKey())
                ->where($targetColumn, $targetId)
                ->firstOrFail();

            return [$favorite->load($relations), false];
        }
    }

    public function remove(User $user, ProfessionalProfile $professionalProfile): void
    {
        Favorite::query()
            ->where('user_id', $user->getKey())
            ->where('professional_profile_id', $professionalProfile->getKey())
            ->delete();
    }

    public function removeFavorite(User $user, Favorite $favorite): void
    {
        Favorite::query()
            ->whereKey($favorite->getKey())
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
