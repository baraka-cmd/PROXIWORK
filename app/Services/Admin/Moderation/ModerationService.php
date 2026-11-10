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
use App\Models\ReviewResponse;
use App\Models\Service;
use App\Models\User;
use App\Services\Admin\Report\AdminReportManagementService;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModerationService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly AdminReportManagementService $reportManagement,
    ) {}

    public function resolveTarget(string $type, int $id): Model
    {
        $map = [
            'profile' => Profile::class,
            'professional_profile' => ProfessionalProfile::class,
            'service' => Service::class,
            'message' => Message::class,
            'review' => Review::class,
            'review_response' => ReviewResponse::class,
        ];

        $class = $map[$type] ?? null;
        if ($class === null) {
            throw ValidationException::withMessages(['target_type' => 'Cible de signalement non supportée.']);
        }

        $target = $class::query()->find($id);
        if ($target === null) {
            throw ValidationException::withMessages(['target_id' => 'La cible demandée est introuvable.']);
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
        return DB::transaction(function () use ($reporter, $type, $id, $reason, $description, $request): Report {
            $target = $this->resolveTarget($type, $id);
            $lockedTarget = $target->newQuery()->lockForUpdate()->findOrFail($target->getKey());

            if ($this->ownsTarget($reporter, $lockedTarget)) {
                throw ValidationException::withMessages(['target_id' => 'Vous ne pouvez pas signaler votre propre contenu.']);
            }

            if (
                $lockedTarget instanceof Message
                && $lockedTarget->conversation()->whereHas('participants', fn ($q) => $q->whereKey($reporter->getKey()))->exists() === false
            ) {
                throw ValidationException::withMessages(['target_id' => 'Vous n’êtes pas autorisé à signaler ce message.']);
            }

            $existingOpenReport = Report::query()
                ->where('reporter_id', $reporter->getKey())
                ->where('target_type', $lockedTarget->getMorphClass())
                ->where('target_id', $lockedTarget->getKey())
                ->whereIn('status', [ReportStatus::PENDING->value, ReportStatus::UNDER_REVIEW->value])
                ->exists();

            if ($existingOpenReport) {
                throw ValidationException::withMessages(['target_id' => 'Un signalement est déjà en cours pour ce contenu.']);
            }

            $report = Report::create([
                'reporter_id' => $reporter->getKey(),
                'target_type' => $lockedTarget->getMorphClass(),
                'target_id' => $lockedTarget->getKey(),
                'reason_code' => $reason,
                'description' => $description,
                'status' => ReportStatus::PENDING,
                'priority' => ReportPriority::NORMAL,
            ]);

            $this->audit->record('report.created', $report, $reporter, [
                'reason_code' => $reason,
                'target_type' => $report->target_type,
                'target_id' => $report->target_id,
            ], $request);

            return $report->load(['reporter', 'target']);
        }, attempts: 3);
    }

    public function assign(User $actor, Report $report, User $assignee, Request $request): Report
    {
        return $this->reportManagement->assign($report, $assignee, $actor, $request);
    }

    public function start(User $actor, Report $report, Request $request): Report
    {
        return $this->reportManagement->startReview($report, $actor, $request);
    }

    public function finish(User $actor, Report $report, ReportStatus $status, ?string $note, Request $request): Report
    {
        return match ($status) {
            ReportStatus::RESOLVED => $this->reportManagement->resolve(
                $report,
                $note ?? '',
                $actor,
                $request,
            ),
            ReportStatus::DISMISSED => $this->reportManagement->dismiss(
                $report,
                $note ?? '',
                $actor,
                $request,
            ),
            default => throw ValidationException::withMessages(['status' => 'Statut final invalide.']),
        };
    }

    public function act(
        User $actor,
        Report $report,
        ModerationActionType $type,
        ?string $reason,
        ?string $note,
        Request $request,
    ): ModerationAction {
        if (blank($note) || mb_strlen(trim($note)) < 10) {
            throw ValidationException::withMessages(['note' => 'Une justification d’au moins 10 caractères est obligatoire.']);
        }

        $this->reportManagement->moderate($report, $type, $actor, $request, $reason, $note);

        return $report->moderationActions()
            ->where('action_type', $type->value)
            ->latest('id')
            ->firstOrFail()
            ->load(['moderator', 'target']);
    }

    private function ownsTarget(User $user, Model $target): bool
    {
        return ($target instanceof Profile && (int) $target->user_id === (int) $user->getKey())
            || ($target instanceof ProfessionalProfile && (int) $target->user_id === (int) $user->getKey())
            || ($target instanceof Service && (int) ($target->professionalProfile?->user_id) === (int) $user->getKey())
            || ($target instanceof Review && (int) $target->client_id === (int) $user->getKey())
            || ($target instanceof ReviewResponse && (int) ($target->professional?->user_id) === (int) $user->getKey())
            || ($target instanceof Message && (int) $target->sender_id === (int) $user->getKey());
    }
}
