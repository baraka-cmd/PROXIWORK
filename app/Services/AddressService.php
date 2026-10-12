<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressService
{
    /**
     * Serialize address writes on the owning user row so concurrent requests
     * cannot leave multiple default addresses for the same client.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): Address
    {
        return DB::transaction(function () use ($user, $attributes): Address {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $hasAddress = $lockedUser->addresses()->exists();
            $requestedDefault = filter_var(
                $attributes['is_default'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );
            unset($attributes['is_default']);

            $isDefault = $requestedDefault || ! $hasAddress;

            if ($isDefault) {
                $lockedUser->addresses()
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $address = $lockedUser->addresses()->create([
                ...$attributes,
                'is_default' => $isDefault,
            ]);

            return $address->refresh();
        }, attempts: 3);
    }

    /**
     * Update a client's address without mutating any service-request/order snapshot.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, Address $address, array $attributes): Address
    {
        return DB::transaction(function () use ($user, $address, $attributes): Address {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ownedAddress = $lockedUser->addresses()
                ->whereKey($address->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $wasDefault = $ownedAddress->is_default;
            $hasDefaultInput = array_key_exists('is_default', $attributes);
            $requestedDefault = $hasDefaultInput
                ? filter_var($attributes['is_default'], FILTER_VALIDATE_BOOLEAN)
                : $wasDefault;

            unset($attributes['is_default']);

            if ($requestedDefault) {
                $lockedUser->addresses()
                    ->whereKeyNot($ownedAddress->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $ownedAddress->update([
                ...$attributes,
                'is_default' => $requestedDefault,
            ]);

            if (! $requestedDefault && ($wasDefault || ! $lockedUser->addresses()->where('is_default', true)->exists())) {
                $replacement = $lockedUser->addresses()
                    ->whereKeyNot($ownedAddress->getKey())
                    ->latest('id')
                    ->first();

                if ($replacement !== null) {
                    $replacement->update(['is_default' => true]);
                } else {
                    // The sole remaining address is always the default.
                    $ownedAddress->update(['is_default' => true]);
                }
            }

            return $ownedAddress->refresh();
        }, attempts: 3);
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
        }, attempts: 3);
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

            $hasOperationalRequest = ServiceRequest::query()
                ->where('address_id', $ownedAddress->getKey())
                ->where(function ($query): void {
                    $query->whereIn('status', ['draft', 'requested', 'quoted'])
                        ->orWhere(function ($accepted): void {
                            $accepted->where('status', 'accepted')
                                ->whereDoesntHave('order');
                        });
                })
                ->exists();

            if ($hasOperationalRequest) {
                throw ValidationException::withMessages([
                    'address' => 'Cette adresse est encore utilisée par une demande ou une commande en préparation. Modifiez le lieu de la demande ou attendez la fin de cette étape avant de la supprimer.',
                ]);
            }

            $wasDefault = $ownedAddress->is_default;
            $ownedAddress->delete();

            if ($wasDefault || ! $lockedUser->addresses()->where('is_default', true)->exists()) {
                $replacement = $lockedUser->addresses()->latest('id')->first();

                if ($replacement !== null) {
                    $replacement->update(['is_default' => true]);
                }
            }
        }, attempts: 3);
    }
}
