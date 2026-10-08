<?php

declare(strict_types=1);

namespace App\Services\Admin\Report;

use App\Enums\ModerationActionType;
use App\Enums\ReportStatus;
use App\Models\ProfessionalProfile;
use App\Models\Report;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use App\Services\Admin\Professional\AdminProfessionalService;
use App\Services\Admin\Service\AdminServiceManagementService;
use App\Services\Admin\User\AdminUserService;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminReportManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly AdminUserService $userService,
        private readonly AdminProfessionalService $professionalService,
        private readonly AdminServiceManagementService $serviceService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Report::query()
            ->with(['reporter:id,name,email', 'assignee:id,name', 'target'])
            ->withCount('moderationActions');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($query) use ($search): void {
                $query->where('reason_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('reporter', function ($reporter) use ($search): void {
                        $reporter->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        foreach (['status', 'priority', 'target_type', 'assigned_to'] as $filter) {
            if (($value = $filters[$filter] ?? null) !== null && $value !== '') {
                $query->where($filter, $value);
            }
        }

        $query->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', $date));
        $query->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', $date));

        $sort = $filters['sort'] ?? '-created_at';
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    public function assignableUsers()
    {
        return User::query()
            ->where('account_status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'reports.manage'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function show(Report $report): Report
    {
        return $report->load([
            'reporter:id,name,email',
            'assignee:id,name,email',
            'resolver:id,name,email',
            'target',
            'moderationActions' => fn ($query) => $query->latest()->with('moderator:id,name'),
        ]);
    }

    public function assign(Report $report, User $assignee, User $actor, Request $request): Report
    {
        return DB::transaction(function () use ($report, $assignee, $actor, $request): Report {
            $target = Report::query()->lockForUpdate()->findOrFail($report->getKey());

            if (! $assignee->isActive() || ! $assignee->hasPermissionTo('reports.manage')) {
                throw ValidationException::withMessages([
                    'assigned_to' => ['Cet utilisateur ne peut pas traiter les signalements.'],
                ]);
            }

            $target->assigned_to = $assignee->getKey();
            $target->save();

            $this->auditLogService->record(
                'admin.report.assigned',
                $target,
                $actor,
                ['assigned_to' => $assignee->getKey()],
                $request,
            );

            return $target->fresh(['reporter', 'assignee']);
        });
    }

    public function startReview(Report $report, User $actor, Request $request): Report
    {
        return $this->transition($report, ReportStatus::PENDING, ReportStatus::UNDER_REVIEW, $actor, $request);
    }

    public function resolve(Report $report, string $note, User $actor, Request $request): Report
    {
        return DB::transaction(function () use ($report, $note, $actor, $request): Report {
            $target = Report::query()->lockForUpdate()->findOrFail($report->getKey());

            if ($target->status !== ReportStatus::UNDER_REVIEW) {
                throw ValidationException::withMessages([
                    'status' => ['Seul un signalement en cours de revue peut être résolu.'],
                ]);
            }

            $target->forceFill([
                'status' => ReportStatus::RESOLVED,
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now(),
                'resolution_note' => $note,
            ])->save();

            $this->auditLogService->record(
                'admin.report.resolved',
                $target,
                $actor,
                ['resolution_note' => $note],
                $request,
            );

            return $target->fresh(['reporter', 'assignee', 'resolver', 'moderationActions']);
        });
    }

    public function dismiss(Report $report, string $note, User $actor, Request $request): Report
    {
        return DB::transaction(function () use ($report, $note, $actor, $request): Report {
            $target = Report::query()->lockForUpdate()->findOrFail($report->getKey());

            if ($target->status !== ReportStatus::UNDER_REVIEW) {
                throw ValidationException::withMessages([
                    'status' => ['Seul un signalement en cours de revue peut être classé sans suite.'],
                ]);
            }

            $target->forceFill([
                'status' => ReportStatus::DISMISSED,
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now(),
                'resolution_note' => $note,
            ])->save();

            $this->auditLogService->record(
                'admin.report.dismissed',
                $target,
                $actor,
                ['resolution_note' => $note],
                $request,
            );

            return $target->fresh(['reporter', 'assignee', 'resolver', 'moderationActions']);
        });
    }

    public function moderate(
        Report $report,
        ModerationActionType $actionType,
        User $actor,
        Request $request,
        ?string $reasonCode = null,
        ?string $note = null,
    ): Report {
        return DB::transaction(function () use ($report, $actionType, $actor, $request, $reasonCode, $note): Report {
            $target = Report::query()->lockForUpdate()->with('target')->findOrFail($report->getKey());

            if ($target->status !== ReportStatus::UNDER_REVIEW) {
                throw ValidationException::withMessages([
                    'status' => ['Le signalement doit être en cours de revue avant une action de modération.'],
                ]);
            }

            if ($target->moderationActions()->where('action_type', $actionType->value)->exists()) {
                throw ValidationException::withMessages([
                    'action_type' => ['Cette action a déjà été appliquée à ce signalement.'],
                ]);
            }

            $this->assertActionTarget($target, $actionType);

            match ($actionType) {
                ModerationActionType::WARNING => null,
                ModerationActionType::HIDE_REVIEW => $this->hideReview($target->target, $actor, $request),
                ModerationActionType::UNPUBLISH_SERVICE => $this->serviceService->unpublish($target->target, $actor),
                ModerationActionType::SUSPEND_USER => $this->userService->suspend($target->target, $actor, $request),
                ModerationActionType::SUSPEND_PROFESSIONAL => $this->professionalService->suspend($target->target, $actor, $request),
            };

            $target->moderationActions()->create([
                'moderator_id' => $actor->getKey(),
                'action_type' => $actionType->value,
                'reason_code' => $reasonCode,
                'note' => $note,
                'target_type' => $target->target_type,
                'target_id' => $target->target_id,
            ]);

            $this->auditLogService->record(
                'admin.report.moderated',
                $target,
                $actor,
                [
                    'action_type' => $actionType->value,
                    'reason_code' => $reasonCode,
                    'target_type' => $target->target_type,
                    'target_id' => $target->target_id,
                ],
                $request,
            );

            return $target->fresh(['reporter', 'assignee', 'resolver', 'moderationActions.moderator', 'target']);
        });
    }

    private function transition(
        Report $report,
        ReportStatus $from,
        ReportStatus $to,
        User $actor,
        Request $request,
    ): Report {
        return DB::transaction(function () use ($report, $from, $to, $actor, $request): Report {
            $target = Report::query()->lockForUpdate()->findOrFail($report->getKey());

            if ($target->status !== $from) {
                throw ValidationException::withMessages([
                    'status' => [sprintf('Transition impossible : %s → %s.', $target->status->value, $to->value)],
                ]);
            }

            $target->status = $to;
            $target->save();

            $this->auditLogService->record(
                'admin.report.status_changed',
                $target,
                $actor,
                ['from_status' => $from->value, 'to_status' => $to->value],
                $request,
            );

            return $target->fresh(['reporter', 'assignee']);
        });
    }

    private function assertActionTarget(Report $report, ModerationActionType $actionType): void
    {
        $target = $report->target;

        $valid = match ($actionType) {
            ModerationActionType::WARNING => $target instanceof User
                || $target instanceof ProfessionalProfile
                || $target instanceof Service
                || $target instanceof Review,
            ModerationActionType::HIDE_REVIEW => $target instanceof Review,
            ModerationActionType::UNPUBLISH_SERVICE => $target instanceof Service,
            ModerationActionType::SUSPEND_USER => $target instanceof User,
            ModerationActionType::SUSPEND_PROFESSIONAL => $target instanceof ProfessionalProfile,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'action_type' => ['Cette action de modération n’est pas compatible avec la ressource signalée.'],
            ]);
        }
    }

    private function hideReview(Review $review, User $actor, Request $request): void
    {
        $review->forceFill([
            'status' => 'hidden',
            'moderated_by' => $actor->getKey(),
            'moderated_at' => now(),
        ])->save();

        $this->auditLogService->record(
            'admin.review.hidden',
            $review,
            $actor,
            [],
            $request,
        );
    }
}