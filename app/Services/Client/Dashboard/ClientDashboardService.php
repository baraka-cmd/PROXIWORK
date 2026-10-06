<?php

declare(strict_types=1);

namespace App\Services\Client\Dashboard;

use App\Models\User;

class ClientDashboardService
{
    public function getFor(User $user): array
    {
        $user->loadMissing('profile');

        $addressesCount = $user->addresses()->count();
        $defaultAddress = $user->addresses()
            ->where('is_default', true)
            ->first([
                'id',
                'label',
                'recipient_name',
                'contact_phone',
                'province',
                'city',
                'commune',
                'neighborhood',
                'address_line_1',
            ]);

        $favoritesCount = $user->favorites()->count();
        $unreadNotifications = $user->unreadNotifications()->count();

        return [
            'profile' => [
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'details' => $user->profile?->only([
                    'first_name',
                    'last_name',
                    'phone',
                    'bio',
                    'locale',
                    'timezone',
                ]),
            ],
            'addresses' => [
                'total' => $addressesCount,
                'default' => $defaultAddress?->toArray(),
            ],
            'favorites' => [
                'count' => $favoritesCount,
            ],
            'notifications' => [
                'unread' => $unreadNotifications,
            ],
            'pending_actions' => $this->pendingActions(
                $user->profile !== null,
                $addressesCount,
                $unreadNotifications,
            ),
        ];
    }

    private function pendingActions(
        bool $hasProfile,
        int $addressesCount,
        int $unreadNotifications,
    ): array {
        $actions = [];

        if (! $hasProfile) {
            $actions[] = [
                'type' => 'profile',
                'priority' => 'normal',
                'message' => 'Complétez votre profil.',
            ];
        }

        if ($addressesCount === 0) {
            $actions[] = [
                'type' => 'addresses',
                'priority' => 'normal',
                'message' => 'Ajoutez votre adresse principale.',
            ];
        }

        if ($unreadNotifications > 0) {
            $actions[] = [
                'type' => 'notifications',
                'priority' => 'normal',
                'message' => 'Vous avez des notifications non lues.',
            ];
        }

        return $actions;
    }
}
