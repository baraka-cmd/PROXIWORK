<?php

declare(strict_types=1);

namespace App\Services\ServiceRequest;

use App\Enums\ServiceRequestStatus;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRequestService
{
    public function create(User $client, array $attributes): ServiceRequest
    {
        return DB::transaction(function () use ($client, $attributes): ServiceRequest {
            $service = Service::query()
                ->with('professionalProfile')
                ->lockForUpdate()
                ->findOrFail($attributes['service_id']);

            if ($service->status->value !== 'published' || $service->published_at === null) {
                throw ValidationException::withMessages([
                    'service_id' => 'Le service sélectionné n’est plus disponible.',
                ]);
            }

            if ($service->professionalProfile === null) {
                throw ValidationException::withMessages([
                    'service_id' => 'Le professionnel associé au service est introuvable.',
                ]);
            }

            if (! empty($attributes['address_id'])) {
                $ownedAddress = $client->addresses()->whereKey($attributes['address_id'])->exists();

                if (! $ownedAddress) {
                    throw ValidationException::withMessages([
                        'address_id' => 'Cette adresse ne vous appartient pas.',
                    ]);
                }
            }

            $this->validateBudget($attributes);

            $request = ServiceRequest::query()->forceCreate([
                ...collect($attributes)->only([
                    'service_id',
                    'address_id',
                    'title',
                    'description',
                    'budget_min',
                    'budget_max',
                    'currency',
                    'desired_at',
                ])->all(),
                'client_id' => $client->getKey(),
                'professional_id' => $service->professionalProfile->getKey(),
                'status' => ServiceRequestStatus::DRAFT,
            ]);

            return $request->load(['service', 'professional.user', 'address']);
        });
    }

    public function update(ServiceRequest $request, array $attributes): ServiceRequest
    {
        return DB::transaction(function () use ($request, $attributes): ServiceRequest {
            $locked = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($locked->status !== ServiceRequestStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Seule une demande en brouillon peut être modifiée.',
                ]);
            }

            if (! empty($attributes['address_id']) && ! $locked->client()->whereKey($request->client_id)->exists()) {
                throw ValidationException::withMessages([
                    'address_id' => 'Cette adresse ne vous appartient pas.',
                ]);
            }

            $this->validateBudget($attributes, $locked);

            $locked->update(collect($attributes)->only([
                'address_id',
                'title',
                'description',
                'budget_min',
                'budget_max',
                'currency',
                'desired_at',
            ])->all());

            return $locked->refresh()->load(['service', 'professional.user', 'address']);
        });
    }

    public function clientRequests(User $client, int $perPage = 15, ?ServiceRequestStatus $status = null)
    {
        $query = $this->baseQuery()
            ->where('client_id', $client->getKey());

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->latest('id')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }

    public function professionalRequests(User $professionalUser, int $perPage = 15)
    {
        return $this->baseQuery()
            ->whereHas('professional', fn (Builder $query) => $query->where('user_id', $professionalUser->getKey()))
            ->latest('id')
            ->paginate(min(max($perPage, 1), 100))
            ->withQueryString();
    }

    private function validateBudget(array $attributes, ?ServiceRequest $existing = null): void
    {
        $min = array_key_exists('budget_min', $attributes) ? $attributes['budget_min'] : $existing?->budget_min;
        $max = array_key_exists('budget_max', $attributes) ? $attributes['budget_max'] : $existing?->budget_max;

        if ($min !== null && $max !== null && (float) $max < (float) $min) {
            throw ValidationException::withMessages([
                'budget_max' => 'Le budget maximum doit être supérieur ou égal au budget minimum.',
            ]);
        }
    }

    private function baseQuery(): Builder
    {
        return ServiceRequest::query()->with([
            'service',
            'professional.user',
            'address',
        ]);
    }
}
