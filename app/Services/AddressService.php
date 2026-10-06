<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function create(User $user, array $attributes): Address
    {
        return DB::transaction(function () use ($user, $attributes): Address {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $hasAddress = $lockedUser->addresses()->exists();

            return $lockedUser->addresses()->create([
                ...$attributes,
                'is_default' => ! $hasAddress,
            ]);
        });
    }

    public function setDefault(User $user, Address $address): Address
    {
        return DB::transaction(function () use ($user, $address): Address {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ownedAddress = $lockedUser->addresses()
                ->whereKey($address->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->addresses()
                ->whereKeyNot($ownedAddress->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $ownedAddress->update(['is_default' => true]);

            return $ownedAddress->refresh();
        });
    }

    public function delete(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ownedAddress = $lockedUser->addresses()
                ->whereKey($address->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $wasDefault = $ownedAddress->is_default;
            $ownedAddress->delete();

            if ($wasDefault) {
                $replacement = $lockedUser->addresses()
                    ->latest('id')
                    ->first();

                if ($replacement !== null) {
                    $replacement->update(['is_default' => true]);
                }
            }
        });
    }
}
