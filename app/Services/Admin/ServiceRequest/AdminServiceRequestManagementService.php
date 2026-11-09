<?php

declare(strict_types=1);

namespace App\Services\Admin\ServiceRequest;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminServiceRequestManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ServiceRequest::query()->with([
            'client',
            'professional.user',
            'service',
            'address',
        ]);

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['professional_id'])) {
            $query->where('professional_id', $filters['professional_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('professional.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('service', fn ($service) => $service->where('category_id', $filters['category_id']));
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['requested_from'])) {
            $query->whereDate('requested_at', '>=', $filters['requested_from']);
        }

        if (! empty($filters['requested_to'])) {
            $query->whereDate('requested_at', '<=', $filters['requested_to']);
        }

        return $query->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function show(ServiceRequest $request): ServiceRequest
    {
        return $request->load([
            'client',
            'professional.user',
            'service.category',
            'address',
            'statusHistories.actor',
            'quotation.offers',
            'order',
        ]);
    }

    public function cancel(ServiceRequest $request, User $actor, ?string $reason = null): ServiceRequest
    {
        return $this->transition($request, $actor, [ServiceRequestStatus::DRAFT, ServiceRequestStatus::REQUESTED], ServiceRequestStatus::CANCELLED, $reason);
    }

    public function reject(ServiceRequest $request, User $actor, ?string $reason = null): ServiceRequest
    {
        return $this->transition($request, $actor, [ServiceRequestStatus::REQUESTED], ServiceRequestStatus::REJECTED, $reason);
    }

    private function transition(ServiceRequest $request, User $actor, array $from, ServiceRequestStatus $to, ?string $reason): ServiceRequest
    {
        return DB::transaction(function () use ($request, $actor, $from, $to, $reason): ServiceRequest {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => 'La transition administrative demandée n’est pas autorisée depuis l’état actuel.',
                ]);
            }

            $previous = $locked->status;
            $locked->forceFill(['status' => $to])->save();
            $locked->statusHistories()->create([
                'from_status' => $previous->value,
                'to_status' => $to->value,
                'changed_by' => $actor->getKey(),
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'service', 'address', 'statusHistories.actor']);
        });
    }
}
