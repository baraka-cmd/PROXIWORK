<?php

declare(strict_types=1);

namespace App\Services\Professional\Dashboard;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class ProfessionalDashboardService
{
    public function getFor(User $user): array
    {
        $profile = $user->professionalProfile()
            ->with(['user.profile', 'verificationReviews' => fn ($query) => $query->latest()])
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
            'verification_note' => $profile->verificationReviews->first()?->note,
            'verification_reason_code' => $profile->verificationReviews->first()?->reason_code,
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

        $verificationStatus = $profile->verification_status;

        if ($verificationStatus === ProfessionalVerificationStatus::PENDING) {
            $actions->push([
                'type' => 'verification',
                'priority' => 'normal',
                'message' => 'Votre dossier a été transmis et attend la revue de l’administration.',
            ]);
        } elseif ($verificationStatus === ProfessionalVerificationStatus::UNDER_REVIEW) {
            $actions->push([
                'type' => 'verification',
                'priority' => 'normal',
                'message' => 'Votre dossier est en cours de vérification. Votre profil reste privé pendant cette étape.',
            ]);
        } elseif ($verificationStatus === ProfessionalVerificationStatus::NEEDS_INFORMATION) {
            $actions->push([
                'type' => 'verification',
                'priority' => 'high',
                'message' => 'Des informations complémentaires sont demandées. Consultez la décision et complétez votre dossier.',
            ]);
        } elseif ($verificationStatus === ProfessionalVerificationStatus::REJECTED) {
            $actions->push([
                'type' => 'verification',
                'priority' => 'high',
                'message' => 'La vérification a été rejetée. Consultez le motif communiqué par l’administration.',
            ]);
        }

        if ($profile->services_count === 0) {
            $actions->push([
                'type' => 'services',
                'priority' => 'normal',
                'message' => 'Créez votre premier service professionnel.',
            ]);
        } elseif (
            $verificationStatus === ProfessionalVerificationStatus::VERIFIED
            && $profile->published_services_count === 0
        ) {
            $actions->push([
                'type' => 'services',
                'priority' => 'normal',
                'message' => 'Votre profil est vérifié. Préparez et publiez un service lorsque ses informations sont prêtes.',
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
