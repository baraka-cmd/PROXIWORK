<?php

declare(strict_types=1);

namespace App\Services\Admin\Service;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\ProfessionalService\ServicePublicationEligibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminServiceManagementService
{
    public function __construct(
        private readonly ServicePublicationEligibility $publicationEligibility,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Service::query()
            ->with([
                'professionalProfile.user.roles',
                'category',
            ])
            ->withCount('serviceRequests');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('professionalProfile.user', function ($user) use ($search): void {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['moderation_status'])) {
            $query->where('moderation_status', $filters['moderation_status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['pricing_type'])) {
            $query->where('pricing_type', $filters['pricing_type']);
        }

        if (! empty($filters['professional_id'])) {
            $query->where('professional_profile_id', $filters['professional_id']);
        }

        $sort = $filters['sort'] ?? '-created_at';

        $query
            ->orderBy(
                $sort === 'title' ? 'title' : 'created_at',
                $sort === 'title' ? 'asc' : 'desc',
            )
            ->orderByDesc('id');

        return $query
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function show(Service $service): Service
    {
        return $service
            ->load([
                'professionalProfile.user.roles',
                'professionalProfile.skills',
                'category',
                'skills',
                'images',
                'serviceRequests.client',
                'moderationDecisions.actor',
                'moderator',
            ])
            ->loadCount('serviceRequests');
    }

    public function publish(Service $service, User $actor): Service
    {
        return $this->transition(
            $service,
            $actor,
            [ServiceStatus::DRAFT, ServiceStatus::UNPUBLISHED],
            ServiceStatus::PUBLISHED,
        );
    }

    public function unpublish(Service $service, User $actor): Service
    {
        return $this->transition(
            $service,
            $actor,
            [ServiceStatus::PUBLISHED],
            ServiceStatus::UNPUBLISHED,
        );
    }

    public function archive(Service $service, User $actor): Service
    {
        return $this->transition(
            $service,
            $actor,
            [ServiceStatus::DRAFT, ServiceStatus::UNPUBLISHED],
            ServiceStatus::ARCHIVED,
        );
    }

    private function transition(
        Service $service,
        User $actor,
        array $from,
        ServiceStatus $to,
    ): Service {
        $updated = DB::transaction(function () use ($service, $actor, $from, $to): Service {
            $locked = Service::query()
                ->lockForUpdate()
                ->findOrFail($service->getKey());

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => 'La transition demandée n’est pas autorisée depuis l’état actuel.',
                ]);
            }

            if ($to === ServiceStatus::PUBLISHED) {
                $this->publicationEligibility->assertAdminCanPublish($locked);
                $locked->forceFill(['published_at' => now()])->save();
            } else {
                $locked->forceFill(['published_at' => null])->save();
            }

            $locked->forceFill(['status' => $to])->save();

            return $locked
                ->refresh()
                ->load(['professionalProfile.user', 'category', 'skills', 'images']);
        });

        $this->auditLogService->record(
            match ($to) {
                ServiceStatus::PUBLISHED => 'admin_service_published',
                ServiceStatus::UNPUBLISHED => 'admin_service_unpublished',
                ServiceStatus::ARCHIVED => 'admin_service_archived',
                default => 'admin_service_status_changed',
            },
            $updated,
            $actor,
            ['from' => array_map(fn (ServiceStatus $status) => $status->value, $from), 'to' => $to->value],
        );

        return $updated;
    }

}
