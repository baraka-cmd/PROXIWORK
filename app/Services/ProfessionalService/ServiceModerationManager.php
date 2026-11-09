<?php

declare(strict_types=1);

namespace App\Services\ProfessionalService;

use App\Enums\ServiceModerationStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceModerationDecision;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceModerationManager
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function moderate(Service $service, User $actor, string $action, string $reason): Service
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Le motif doit contenir au moins 10 caractères.',
            ]);
        }

        return DB::transaction(function () use ($service, $actor, $action, $reason): Service {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($locked->status === ServiceStatus::ARCHIVED) {
                throw ValidationException::withMessages([
                    'service' => 'Un service archivé ne peut plus faire l’objet d’une décision de modération.',
                ]);
            }

            $from = $locked->moderation_status;
            $to = $this->targetStatus($action, $from);

            $locked->forceFill([
                'moderation_status' => $to,
                'moderation_reason' => $reason,
                'moderated_by' => $actor->getKey(),
                'moderated_at' => now(),
            ])->save();

            ServiceModerationDecision::query()->create([
                'service_id' => $locked->getKey(),
                'actor_id' => $actor->getKey(),
                'action' => $action,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
            ]);

            $this->auditLogService->record(
                'service_moderation_'.$action,
                $locked,
                $actor,
                ['from' => $from->value, 'to' => $to->value, 'reason' => $reason],
            );

            return $locked->refresh()->load(['moderator', 'moderationDecisions.actor']);
        });
    }

    public function submitForReview(Service $service, User $actor, ?string $note = null): Service
    {
        return DB::transaction(function () use ($service, $actor, $note): Service {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($locked->status === ServiceStatus::ARCHIVED) {
                throw ValidationException::withMessages([
                    'service' => 'Un service archivé ne peut pas être soumis à un nouvel examen.',
                ]);
            }

            $from = $locked->moderation_status;

            if (! in_array($from, [
                ServiceModerationStatus::HIDDEN,
                ServiceModerationStatus::CHANGES_REQUESTED,
                ServiceModerationStatus::REJECTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'moderation' => 'Seul un service masqué, refusé ou en attente de corrections peut être soumis à nouveau.',
                ]);
            }

            $locked->forceFill(['moderation_status' => ServiceModerationStatus::UNDER_REVIEW])->save();

            ServiceModerationDecision::query()->create([
                'service_id' => $locked->getKey(),
                'actor_id' => $actor->getKey(),
                'action' => 'submitted_for_review',
                'from_status' => $from->value,
                'to_status' => ServiceModerationStatus::UNDER_REVIEW->value,
                'reason' => filled($note) ? trim($note) : null,
            ]);

            $this->auditLogService->record(
                'service_submitted_for_moderation',
                $locked,
                $actor,
                ['from' => $from->value, 'to' => ServiceModerationStatus::UNDER_REVIEW->value],
            );

            return $locked->refresh()->load('moderationDecisions.actor');
        });
    }

    private function targetStatus(string $action, ServiceModerationStatus $from): ServiceModerationStatus
    {
        $transitions = [
            'start_review' => [
                ServiceModerationStatus::CLEAR->value => ServiceModerationStatus::UNDER_REVIEW,
            ],
            'approve' => [
                ServiceModerationStatus::UNDER_REVIEW->value => ServiceModerationStatus::CLEAR,
                ServiceModerationStatus::CHANGES_REQUESTED->value => ServiceModerationStatus::CLEAR,
            ],
            'request_changes' => [
                ServiceModerationStatus::CLEAR->value => ServiceModerationStatus::CHANGES_REQUESTED,
                ServiceModerationStatus::UNDER_REVIEW->value => ServiceModerationStatus::CHANGES_REQUESTED,
            ],
            'hide' => [
                ServiceModerationStatus::CLEAR->value => ServiceModerationStatus::HIDDEN,
                ServiceModerationStatus::UNDER_REVIEW->value => ServiceModerationStatus::HIDDEN,
                ServiceModerationStatus::CHANGES_REQUESTED->value => ServiceModerationStatus::HIDDEN,
            ],
            'reject' => [
                ServiceModerationStatus::UNDER_REVIEW->value => ServiceModerationStatus::REJECTED,
                ServiceModerationStatus::CHANGES_REQUESTED->value => ServiceModerationStatus::REJECTED,
            ],
            'restore' => [
                ServiceModerationStatus::HIDDEN->value => ServiceModerationStatus::CLEAR,
                ServiceModerationStatus::REJECTED->value => ServiceModerationStatus::CLEAR,
            ],
        ];

        $target = $transitions[$action][$from->value] ?? null;

        if (! $target instanceof ServiceModerationStatus) {
            throw ValidationException::withMessages([
                'decision' => 'Cette décision n’est pas autorisée depuis l’état actuel de modération.',
            ]);
        }

        return $target;
    }
}
