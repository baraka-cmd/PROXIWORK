<?php

declare(strict_types=1);

namespace App\Services\Professional\Dashboard;

use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class ProfessionalDashboardService
{
    public function getFor(User $user): array
    {
        $profile = $user->professionalProfile()
            ->with(['user.profile'])
            ->withCount([
                'services',
                'services as published_services_count' => fn ($query) => $query
                    ->where('status', ServiceStatus::PUBLISHED->value)
                    ->whereNotNull('published_at'),
                'services as draft_services_count' => fn ($query) => $query
                    ->where('status', ServiceStatus::DRAFT->value),
                'services as unpublished_services_count' => fn ($query) => $query
                    ->where('status', ServiceStatus::UNPUBLISHED->value),
                'services as archived_services_count' => fn ($query) => $query
                    ->where('status', ServiceStatus::ARCHIVED->value),
                'favorites as received_favorites_count',
            ])
            ->firstOrFail();

        $unreadNotifications = $user->unreadNotifications()->count();

        return [
            'profile' => $this->profile($profile),
            'services' => [
                'total' => $profile->services_count,
                'published' => $profile->published_services_count,
                'draft' => $profile->draft_services_count,
                'unpublished' => $profile->unpublished_services_count,
                'archived' => $profile->archived_services_count,
            ],
            'engagement' => [
                'received_favorites' => $profile->received_favorites_count,
                'rating_average' => $profile->rating_average,
                'rating_count' => $profile->rating_count,
            ],
            'notifications' => [
                'unread' => $unreadNotifications,
            ],
            'pending_actions' => $this->pendingActions($profile, $unreadNotifications),
        ];
    }

    private function profile(ProfessionalProfile $profile): array
    {
        return [
            'id' => $profile->getKey(),
            'professional_title' => $profile->professional_title,
            'verification_status' => $profile->verification_status?->value,
            'availability_status' => $profile->availability_status?->value,
            'rating_average' => $profile->rating_average,
            'rating_count' => $profile->rating_count,
            'user' => [
                'id' => $profile->user->getKey(),
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'profile' => $profile->user->profile?->only([
                    'first_name',
                    'last_name',
                    'phone',
                    'bio',
                    'locale',
                    'timezone',
                ]),
            ],
        ];
    }

    private function pendingActions(ProfessionalProfile $profile, int $unreadNotifications): Collection
    {
        $actions = collect();

        if ($profile->services_count === 0) {
            $actions->push([
                'type' => 'services',
                'priority' => 'normal',
                'message' => 'Créez votre premier service professionnel.',
            ]);
        }

        if ($profile->services_count > 0 && $profile->published_services_count === 0) {
            $actions->push([
                'type' => 'services',
                'priority' => 'normal',
                'message' => 'Publiez au moins un service pour apparaître dans le catalogue.',
            ]);
        }

        if ($unreadNotifications > 0) {
            $actions->push([
                'type' => 'notifications',
                'priority' => 'normal',
                'message' => 'Vous avez des notifications non lues.',
            ]);
        }

        return $actions->values();
    }
}
