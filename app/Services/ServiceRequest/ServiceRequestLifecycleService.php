<?php

declare(strict_types=1);

namespace App\Services\ServiceRequest;

use App\Enums\ServiceRequestStatus;
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
                'statusHistories.actor',
            ]);
        });
    }
}
