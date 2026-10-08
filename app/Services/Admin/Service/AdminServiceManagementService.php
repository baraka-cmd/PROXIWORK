<?php

declare(strict_types=1);

namespace App\Services\Admin\Service;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminServiceManagementService
{
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
        return DB::transaction(function () use ($service, $from, $to): Service {
            $locked = Service::query()
                ->lockForUpdate()
                ->findOrFail($service->getKey());

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => 'La transition demandée n’est pas autorisée depuis l’état actuel.',
                ]);
            }

            if ($to === ServiceStatus::PUBLISHED) {
                $this->assertPublishable($locked);
                $locked->forceFill(['published_at' => now()])->save();
            } else {
                $locked->forceFill(['published_at' => null])->save();
            }

            $locked->forceFill(['status' => $to])->save();

            return $locked
                ->refresh()
                ->load(['professionalProfile.user', 'category', 'skills', 'images']);
        });
    }

    private function assertPublishable(Service $service): void
    {
        if ($service->professionalProfile === null || $service->category === null) {
            throw ValidationException::withMessages([
                'service' => 'Le service doit avoir un professionnel et une catégorie.',
            ]);
        }

        if (trim((string) $service->title) === '' || trim((string) $service->description) === '') {
            throw ValidationException::withMessages([
                'service' => 'Le service doit avoir un titre et une description.',
            ]);
        }

        if ($service->skills()->where('status', 'active')->doesntExist()) {
            throw ValidationException::withMessages([
                'service' => 'Le service doit posséder au moins une compétence active.',
            ]);
        }

        if ($service->images()->where('is_cover', true)->doesntExist()) {
            throw ValidationException::withMessages([
                'service' => 'Le service doit posséder une image de couverture.',
            ]);
        }

        if ($service->pricing_type->value === 'fixed' && $service->price === null) {
            throw ValidationException::withMessages([
                'service' => 'Le prix fixe est obligatoire.',
            ]);
        }

        if ($service->pricing_type->value === 'from' && $service->price_min === null) {
            throw ValidationException::withMessages([
                'service' => 'Le prix minimum est obligatoire.',
            ]);
        }

        if (
            $service->pricing_type->value === 'range'
            && (
                $service->price_min === null
                || $service->price_max === null
                || (float) $service->price_min > (float) $service->price_max
            )
        ) {
            throw ValidationException::withMessages([
                'service' => 'La fourchette de prix est invalide.',
            ]);
        }
    }
}
