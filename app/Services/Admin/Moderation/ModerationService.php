<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use App\Enums\ModerationActionType;
use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Models\ProfessionalProfile;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModerationService
{
    public function __construct(private AuditLogService $audit)
    {
    }

    public function resolveTarget(string $type, int $id): Model
    {
        $map = [
            'profile' => Profile::class,
            'professional_profile' => ProfessionalProfile::class,
            'service' => Service::class,
            'message' => Message::class,
            'review' => Review::class,
        ];

        $class = $map[$type] ?? null;

        if (!$class) {
            throw ValidationException::withMessages([
                'target_type' => 'Cible de signalement non supportée.',
            ]);
        }

        $target = $class::query()->find($id);

        if (!$target) {
            throw ValidationException::withMessages([
                'target_id' => 'La cible demandée est introuvable.',
            ]);
        }

        return $target;
    }

    public function createReport(
        User $reporter,
        string $type,
        int $id,
        string $reason,
        ?string $description,
        Request $request,
    ): Report {
        $target = $this->resolveTarget($type, $id);

        if ($this->ownsTarget($reporter, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Vous ne pouvez pas signaler votre propre contenu.',
            ]);
        }

        if (
            $target instanceof Message
            && !$target->conversation->participants()->whereKey($reporter->getKey())->exists()
        ) {
            throw ValidationException::withMessages([
                'target_id' => 'Vous n’êtes pas autorisé à signaler ce message.',
            ]);
        }

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'reason_code' => $reason,
            'description' => $description,
            'status' => ReportStatus::PENDING,
            'priority' => ReportPriority::NORMAL,
        ]);

        $this->audit->record(
            'report.created',
            $report,
            $reporter,
            [
                'reason_code' => $reason,
                'target_type' => $report->target_type,
                'target_id' => $report->target_id,
            ],
            $request,
        );

        return $report->load(['reporter', 'target']);
    }

    public function assign(User $actor, Report $report, User $assignee, Request $request): Report
    {
        if (!$assignee->isActive() || !$assignee->hasPermissionTo('reports.manage')) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Le destinataire doit être un modérateur actif autorisé.',
            ]);
        }

        return DB::transaction(function () use ($actor, $report, $assignee, $request): Report {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);

            if (in_array($report->status, [ReportStatus::RESOLVED, ReportStatus::DISMISSED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Un signalement clôturé ne peut plus être assigné.',
                ]);
            }

            $report->update(['assigned_to' => $assignee->id]);

            $this->audit->record(
                'report.assigned',
                $report,
                $actor,
                ['assigned_to' => $assignee->id],
                $request,
            );

            return $report->fresh(['reporter', 'assignee', 'target']);
        });
    }

    public function start(User $actor, Report $report, Request $request): Report
    {
        return DB::transaction(function () use ($actor, $report, $request): Report {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);

            if ($report->status !== ReportStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Seuls les signalements en attente peuvent être mis en revue.',
                ]);
            }

            $report->update(['status' => ReportStatus::UNDER_REVIEW]);

            $this->audit->record('report.review_started', $report, $actor, [], $request);

            return $report->fresh(['reporter', 'assignee', 'target']);
        });
    }

    public function finish(
        User $actor,
        Report $report,
        ReportStatus $status,
        ?string $note,
        Request $request,
    ): Report {
        if (!in_array($status, [ReportStatus::RESOLVED, ReportStatus::DISMISSED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Statut final invalide.',
            ]);
        }

        return DB::transaction(function () use ($actor, $report, $status, $note, $request): Report {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);

            if ($report->status !== ReportStatus::UNDER_REVIEW) {
                throw ValidationException::withMessages([
                    'status' => 'Le signalement doit être en revue avant clôture.',
                ]);
            }

            $report->update([
                'status' => $status,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ]);

            $this->audit->record(
                'report.'.($status === ReportStatus::RESOLVED ? 'resolved' : 'dismissed'),
                $report,
                $actor,
                ['resolution_note' => $note],
                $request,
            );

            return $report->fresh([
                'reporter',
                'assignee',
                'resolver',
                'target',
                'moderationActions',
            ]);
        });
    }

    public function act(
        User $actor,
        Report $report,
        ModerationActionType $type,
        ?string $reason,
        ?string $note,
        Request $request,
    ): ModerationAction {
        return DB::transaction(function () use ($actor, $report, $type, $reason, $note, $request): ModerationAction {
            $report = Report::query()->lockForUpdate()->with('target')->findOrFail($report->id);

            if ($report->status !== ReportStatus::UNDER_REVIEW) {
                throw ValidationException::withMessages([
                    'status' => 'Le signalement doit être en revue avant une action.',
                ]);
            }

            if ($report->moderationActions()->where('action_type', $type->value)->exists()) {
                throw ValidationException::withMessages([
                    'action_type' => 'Cette action a déjà été appliquée à ce signalement.',
                ]);
            }

            $target = $report->target;
            $this->apply($type, $target, $actor, $reason, $note);

            $action = $report->moderationActions()->create([
                'moderator_id' => $actor->id,
                'action_type' => $type,
                'reason_code' => $reason,
                'note' => $note,
                'target_type' => $report->target_type,
                'target_id' => $report->target_id,
            ]);

            $this->audit->record(
                'moderation.action_created',
                $action,
                $actor,
                ['action_type' => $type->value, 'report_id' => $report->id],
                $request,
            );

            return $action->load(['moderator', 'target']);
        });
    }

    private function apply(
        ModerationActionType $type,
        Model $target,
        User $actor,
        ?string $reason,
        ?string $note,
    ): void {
        match ($type) {
            ModerationActionType::WARNING => null,
            ModerationActionType::HIDE_REVIEW => $target instanceof Review
                ? $target->forceFill([
                    'status' => 'hidden',
                    'moderated_at' => now(),
                    'moderated_by' => $actor->id,
                    'moderation_reason' => $reason ?? $note,
                ])->save()
                : throw ValidationException::withMessages([
                    'action_type' => 'Cette action exige un avis.',
                ]),
            ModerationActionType::UNPUBLISH_SERVICE => $target instanceof Service
                ? $target->forceFill([
                    'status' => 'unpublished',
                    'published_at' => null,
                ])->save()
                : throw ValidationException::withMessages([
                    'action_type' => 'Cette action exige un service.',
                ]),
            ModerationActionType::SUSPEND_USER => $this->suspendUser($target),
            ModerationActionType::SUSPEND_PROFESSIONAL => $this->suspendProfessional($target),
        };
    }

    private function suspendUser(Model $target): void
    {
        $user = $target instanceof User ? $target : ($target instanceof Profile ? $target->user : null);

        if (!$user) {
            throw ValidationException::withMessages([
                'action_type' => 'Cette action exige un utilisateur ou un profil.',
            ]);
        }

        $user->update(['account_status' => 'suspended']);
        $user->tokens()->delete();
    }

    private function suspendProfessional(Model $target): void
    {
        if (!$target instanceof ProfessionalProfile) {
            throw ValidationException::withMessages([
                'action_type' => 'Cette action exige un profil professionnel.',
            ]);
        }

        $target->user()->update(['account_status' => 'suspended']);
        $target->user->tokens()->delete();
    }

    private function ownsTarget(User $user, Model $target): bool
    {
        return ($target instanceof Profile && $target->user_id === $user->id)
            || ($target instanceof ProfessionalProfile && $target->user_id === $user->id)
            || ($target instanceof Service && $target->professionalProfile?->user_id === $user->id)
            || ($target instanceof Review && $target->client_id === $user->id)
            || ($target instanceof Message && $target->sender_id === $user->id);
    }
}
