<?php

declare(strict_types=1);

namespace App\Services\ServiceRequest;

use App\Enums\ServiceRequestStatus;
use App\Models\Address;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRequestService
{
    public function create(User $client, array $attributes): ServiceRequest
    {
        $idempotencyKey = $attributes['idempotency_key'] ?? null;

        try {
            return DB::transaction(function () use ($client, $attributes, $idempotencyKey): ServiceRequest {
                if ($idempotencyKey !== null) {
                    $existing = ServiceRequest::query()
                        ->where('client_id', $client->getKey())
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing !== null) {
                        $existing->load([
                            'service',
                            'professional.user',
                            'address',
                            'statusHistories.actor',
                        ]);
                        $existing->setAttribute('_idempotent_replay', true);

                        return $existing;
                    }
                }

                $service = Service::query()
                    ->with(['category', 'professionalProfile.user'])
                    ->lockForUpdate()
                    ->find($attributes['service_id']);

                if ($service === null || ! $service->isRequestableForClient()) {
                    throw ValidationException::withMessages([
                        'service_id' => 'Ce service n’est plus disponible pour recevoir une nouvelle demande.',
                    ]);
                }

                if (! empty($attributes['address_id']) && ! $client->addresses()
                    ->whereKey($attributes['address_id'])
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'address_id' => 'Cette adresse ne vous appartient pas.',
                    ]);
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
                    'idempotency_key' => $idempotencyKey,
                    'client_id' => $client->getKey(),
                    'professional_id' => $service->professionalProfile->getKey(),
                    'status' => ServiceRequestStatus::DRAFT,
                ]);

                $request->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => ServiceRequestStatus::DRAFT->value,
                    'changed_by' => $client->getKey(),
                    'reason' => 'Demande créée en brouillon.',
                    'created_at' => now(),
                ]);

                $request->load([
                    'service',
                    'professional.user',
                    'address',
                    'statusHistories.actor',
                ]);
                $request->setAttribute('_idempotent_replay', false);

                return $request;
            }, attempts: 3);
        } catch (QueryException $exception) {
            // A concurrent retry may win the unique (client, idempotency_key) insert.
            // Return that committed request instead of creating a duplicate or a false error.
            if ($idempotencyKey !== null) {
                $existing = ServiceRequest::query()
                    ->where('client_id', $client->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    $existing->load([
                        'service',
                        'professional.user',
                        'address',
                        'statusHistories.actor',
                    ]);
                    $existing->setAttribute('_idempotent_replay', true);

                    return $existing;
                }
            }

            throw $exception;
        }
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

            if (! empty($attributes['address_id']) && ! Address::query()
                ->whereKey($attributes['address_id'])
                ->where('user_id', $locked->client_id)
                ->exists()) {
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

            return $locked->refresh()->load(['service', 'professional.user', 'address', 'statusHistories.actor']);
        });
    }

    public function setAddress(ServiceRequest $request, User $client, int $addressId): ServiceRequest
    {
        return DB::transaction(function () use ($request, $client, $addressId): ServiceRequest {
            $locked = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($locked->client_id !== $client->getKey()) {
                abort(403, 'Vous ne pouvez pas modifier cette demande.');
            }

            if (! in_array($locked->status, [
                ServiceRequestStatus::REQUESTED,
                ServiceRequestStatus::QUOTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Le lieu d’exécution ne peut être modifié qu’avant l’acceptation du devis.',
                ]);
            }

            $address = Address::query()
                ->whereKey($addressId)
                ->where('user_id', $client->getKey())
                ->first();

            if ($address === null) {
                throw ValidationException::withMessages([
                    'address_id' => 'Sélectionnez une adresse qui vous appartient.',
                ]);
            }

            $locked->forceFill(['address_id' => $address->getKey()])->save();

            return $locked->refresh()->load([
                'service',
                'professional.user',
                'address',
                'statusHistories.actor',
                'quotation.currentOffer',
                'order',
            ]);
        }, attempts: 3);
    }

    /**
     * Query only the authenticated client's requests. Filters are applied in SQL
     * before pagination so private records are never loaded into application memory.
     *
     * @param array<string, mixed> $filters
     */
    public function clientRequests(
        User $client,
        int $perPage = 15,
        ?ServiceRequestStatus $status = null,
        array $filters = [],
    ) {
        $query = $this->baseQuery()
            ->where('client_id', $client->getKey());

        $this->applyFilters($query, $filters, $status);

        return $this->paginate($query, $perPage, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function professionalRequests(User $professionalUser, int $perPage = 15, array $filters = [])
    {
        $query = $this->baseQuery()
            ->whereHas('professional', fn (Builder $profile) => $profile
                ->where('user_id', $professionalUser->getKey()));

        $this->applyFilters($query, $filters, null, true);

        return $this->paginate($query, $perPage, $filters);
    }

    /**
     * Status totals are intentionally independent of the active list filters.
     *
     * @return array<string, int>
     */
    public function clientRequestStats(User $client): array
    {
        $counts = ServiceRequest::query()
            ->where('client_id', $client->getKey())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $counts->sum(),
            'draft' => (int) ($counts[ServiceRequestStatus::DRAFT->value] ?? 0),
            'requested' => (int) ($counts[ServiceRequestStatus::REQUESTED->value] ?? 0),
            'quoted' => (int) ($counts[ServiceRequestStatus::QUOTED->value] ?? 0),
            'accepted' => (int) ($counts[ServiceRequestStatus::ACCEPTED->value] ?? 0),
            'rejected' => (int) ($counts[ServiceRequestStatus::REJECTED->value] ?? 0),
            'cancelled' => (int) ($counts[ServiceRequestStatus::CANCELLED->value] ?? 0),
            'closed' => (int) (
                ($counts[ServiceRequestStatus::ACCEPTED->value] ?? 0)
                + ($counts[ServiceRequestStatus::REJECTED->value] ?? 0)
                + ($counts[ServiceRequestStatus::CANCELLED->value] ?? 0)
            ),
            'awaiting_professional' => (int) ($counts[ServiceRequestStatus::REQUESTED->value] ?? 0),
            'awaiting_client' => (int) ($counts[ServiceRequestStatus::QUOTED->value] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(
        Builder $query,
        array $filters,
        ?ServiceRequestStatus $status = null,
        bool $searchClient = false,
    ): void {
        $selectedStatus = $status?->value ?? ($filters['status'] ?? null);

        if ($selectedStatus === 'closed') {
            $query->whereIn('status', [
                ServiceRequestStatus::ACCEPTED->value,
                ServiceRequestStatus::REJECTED->value,
                ServiceRequestStatus::CANCELLED->value,
            ]);
        } elseif (is_string($selectedStatus) && $selectedStatus !== '') {
            $query->where('status', $selectedStatus);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $referenceId = preg_match('/^PW-REQ-(\\d+)$/i', $search, $matches) === 1
                ? (int) $matches[1]
                : null;

            $query->where(function (Builder $nested) use ($search, $searchClient, $referenceId): void {
                if (ctype_digit($search)) {
                    $nested->whereKey((int) $search);
                } elseif ($referenceId !== null) {
                    $nested->whereKey($referenceId);
                }

                $like = '%'.$search.'%';
                $nested->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('service', fn (Builder $service) => $service->where('title', 'like', $like));

                if ($searchClient) {
                    $nested->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', $like));
                } else {
                    $nested->orWhereHas('professional.user', fn (Builder $user) => $user->where('name', 'like', $like));
                }
            });
        }

        if (! empty($filters['professional_id'])) {
            $query->where('professional_id', (int) $filters['professional_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('service', fn (Builder $service) => $service
                ->where('category_id', (int) $filters['category_id']));
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function paginate(Builder $query, int $perPage, array $filters)
    {
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        return $query
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->paginate(min(max($perPage, 1), 50))
            ->withQueryString();
    }

    private function validateBudget(array $attributes, ?ServiceRequest $existing = null): void
    {
        $min = array_key_exists('budget_min', $attributes) ? $attributes['budget_min'] : $existing?->budget_min;
        $max = array_key_exists('budget_max', $attributes) ? $attributes['budget_max'] : $existing?->budget_max;
        $currency = array_key_exists('currency', $attributes) ? $attributes['currency'] : $existing?->currency;

        if ($min !== null && $max !== null && (float) $max < (float) $min) {
            throw ValidationException::withMessages([
                'budget_max' => 'Le budget maximum doit être supérieur ou égal au budget minimum.',
            ]);
        }

        if (($min !== null || $max !== null) && $currency === null) {
            throw ValidationException::withMessages([
                'currency' => 'La devise est obligatoire lorsqu’un budget est indiqué.',
            ]);
        }
    }

    private function baseQuery(): Builder
    {
        return ServiceRequest::query()->with([
            'service.category',
            'professional.user',
            'address',
            'quotation.currentOffer',
            'order',
        ]);
    }
}
