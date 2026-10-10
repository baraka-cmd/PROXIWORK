<?php

declare(strict_types=1);

namespace App\Services\Admin\Professional;

use App\Enums\UserAccountStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProfessionalService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters)
    {
        $query = ProfessionalProfile::query()
            ->with(['user.roles'])
            ->withCount(['services', 'reviews', 'serviceRequests']);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('professional_title', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($user) use ($search): void {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (($status = $filters['verification_status'] ?? null) !== null) {
            $status = $status instanceof BackedEnum ? $status->value : $status;
            $query->where('verification_status', $status);
        }

        if (($status = $filters['account_status'] ?? null) !== null) {
            $status = $status instanceof BackedEnum ? $status->value : $status;
            $query->whereHas('user', fn ($user) => $user->where('account_status', $status));
        }

        if (($status = $filters['availability_status'] ?? null) !== null) {
            $status = $status instanceof BackedEnum ? $status->value : $status;
            $query->where('availability_status', $status);
        }

        $query->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', $date));
        $query->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', $date));

        $sort = $filters['sort'] ?? '-created_at';
        if ($sort === 'rating' || $sort === '-rating') {
            $query->orderBy('rating_average', $sort === 'rating' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', str_starts_with($sort, '-') ? 'desc' : 'asc');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    public function suspend(ProfessionalProfile $professional, User $actor, Request $request): ProfessionalProfile
    {
        $changed = false;
        $updated = DB::transaction(function () use ($professional, $actor, $request, &$changed): ProfessionalProfile {
            $target = ProfessionalProfile::query()->lockForUpdate()->with('user')->findOrFail($professional->getKey());

            if ($target->user->getKey() === $actor->getKey()) {
                throw ValidationException::withMessages([
                    'professional' => ['Un administrateur ne peut pas suspendre son propre compte professionnel.'],
                ]);
            }

            if ($target->user->account_status === UserAccountStatus::SUSPENDED) {
                return $target;
            }

            $target->user->account_status = UserAccountStatus::SUSPENDED->value;
            $target->user->save();
            $target->forceFill([
                'status' => ProfessionalProfile::STATUS_SUSPENDED,
                'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
            ])->save();
            $target->user->tokens()->delete();
            $changed = true;

            $this->auditLogService->record(
                'admin.professional.suspended',
                $target,
                $actor,
                ['previous_status' => UserAccountStatus::ACTIVE->value, 'new_status' => UserAccountStatus::SUSPENDED->value],
                $request,
            );

            return $target->fresh(['user.roles']);
        });

        if ($changed) {
            $updated->user->notify(new AccountActivityNotification(
                'Compte professionnel suspendu',
                'Votre compte professionnel PROXIWORK a été suspendu par l’administration.',
                'professional_account_suspended',
            ));
        }

        return $updated;
    }

    public function activate(ProfessionalProfile $professional, User $actor, Request $request): ProfessionalProfile
    {
        return DB::transaction(function () use ($professional, $actor, $request): ProfessionalProfile {
            $target = ProfessionalProfile::query()->lockForUpdate()->with('user')->findOrFail($professional->getKey());

            if ($target->user->account_status === UserAccountStatus::ACTIVE) {
                return $target;
            }

            $target->user->account_status = UserAccountStatus::ACTIVE->value;
            $target->user->save();
            $target->forceFill([
                'status' => ProfessionalProfile::STATUS_ACTIVE,
                'visibility' => $target->services()->published()->exists()
                    ? ProfessionalProfile::VISIBILITY_PUBLIC
                    : ProfessionalProfile::VISIBILITY_PRIVATE,
            ])->save();

            $this->auditLogService->record(
                'admin.professional.activated',
                $target,
                $actor,
                ['previous_status' => UserAccountStatus::SUSPENDED->value, 'new_status' => UserAccountStatus::ACTIVE->value],
                $request,
            );

            return $target->fresh(['user.roles']);
        });
    }

    public function show(ProfessionalProfile $professional): ProfessionalProfile
    {
        return $professional->load([
            'user.roles',
            'user.profile',
            'skills',
            'categories',
            'services.skills',
            'documents' => fn ($query) => $query->latest(),
            'verificationReviews' => fn ($q) => $q->latest()->with('admin:id,name'),
        ])->loadCount(['services', 'reviews', 'serviceRequests']);
    }
}
