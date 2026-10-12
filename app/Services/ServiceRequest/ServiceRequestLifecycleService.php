<?php

declare(strict_types=1);

namespace App\Services\ServiceRequest;

use App\Enums\ServiceRequestStatus;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRequestLifecycleService
{
    public function submit(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->transition(
            $request,
            $actor,
            ServiceRequestStatus::DRAFT,
            ServiceRequestStatus::REQUESTED,
            null,
            function (ServiceRequest $locked): void {
                $service = Service::query()
                    ->with(['category', 'professionalProfile.user'])
                    ->lockForUpdate()
                    ->find($locked->service_id);

                if ($service === null || ! $service->isRequestableForClient()) {
                    throw ValidationException::withMessages([
                        'service_id' => 'Ce service n’est plus disponible pour recevoir une nouvelle demande.',
                    ]);
                }

                if ($locked->execution_mode->requiresClientAddress()) {
                    $hasOwnedAddress = $locked->address()
                        ->where('user_id', $locked->client_id)
                        ->exists();
                    $hasSnapshot = $locked->addressSnapshot()->exists();

                    if (! $hasOwnedAddress || ! $hasSnapshot) {
                        throw ValidationException::withMessages([
                            'address_id' => 'Choisissez une adresse d’intervention avant d’envoyer cette demande au professionnel.',
                        ]);
                    }
                }

                $locked->forceFill(['requested_at' => now()])->save();
            },
        );
    }

    public function cancel(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->transition(
            $request,
            $actor,
            [ServiceRequestStatus::DRAFT, ServiceRequestStatus::REQUESTED],
            ServiceRequestStatus::CANCELLED,
        );
    }

    public function rejectByProfessional(
        ServiceRequest $request,
        User $actor,
        ?string $reason = null,
    ): ServiceRequest {
        return $this->transition(
            $request,
            $actor,
            ServiceRequestStatus::REQUESTED,
            ServiceRequestStatus::REJECTED,
            $reason,
        );
    }

    public function rejectByClient(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->transition(
            $request,
            $actor,
            ServiceRequestStatus::QUOTED,
            ServiceRequestStatus::REJECTED,
            'Devis refusé par le client.',
        );
    }

    public function rejectByProfessionalDuringNegotiation(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->transition(
            $request,
            $actor,
            ServiceRequestStatus::QUOTED,
            ServiceRequestStatus::REJECTED,
            'Contre-proposition du client refusée par le professionnel.',
        );
    }

    public function markQuoted(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->transition(
            $request,
            $actor,
            ServiceRequestStatus::REQUESTED,
            ServiceRequestStatus::QUOTED,
        );
    }

    public function accept(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->transition(
            $request,
            $actor,
            ServiceRequestStatus::QUOTED,
            ServiceRequestStatus::ACCEPTED,
        );
    }

    private function transition(
        ServiceRequest $request,
        User $actor,
        ServiceRequestStatus|array $from,
        ServiceRequestStatus $to,
        ?string $reason = null,
        ?callable $beforeSave = null,
    ): ServiceRequest {
        return DB::transaction(function () use ($request, $actor, $from, $to, $reason, $beforeSave): ServiceRequest {
            $locked = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            $allowed = is_array($from) ? $from : [$from];

            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'La transition %s → %s n’est pas autorisée depuis l’état actuel.',
                        $locked->status->value,
                        $to->value,
                    ),
                ]);
            }

            $previous = $locked->status;

            if ($beforeSave !== null) {
                $beforeSave($locked);
            }

            $locked->forceFill(['status' => $to])->save();

            $locked->statusHistories()->create([
                'from_status' => $previous->value,
                'to_status' => $to->value,
                'changed_by' => $actor->getKey(),
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $locked->refresh()->load([
                'service',
                'professional.user',
                'address',
                'addressSnapshot',
                'statusHistories.actor',
            ]);
        });
    }
}
