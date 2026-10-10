<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserRoleAssignmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SessionRevocationService $sessionRevocationService,
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Replace a user's roles atomically, revoke stale credentials and audit the change.
     *
     * @param  array<int, int|string>  $roleIds
     */
    public function sync(User $target, User $actor, array $roleIds, Request $request): User
    {
        return $this->database->transaction(function () use ($target, $actor, $roleIds, $request): User {
            // Lock administrator rows in a stable order so concurrent updates cannot remove
            // the final active administrator at the same time.
            $administrators = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'account_status']);

            $lockedTarget = User::query()->lockForUpdate()->findOrFail($target->getKey());

            if ($actor->is($lockedTarget)) {
                throw ValidationException::withMessages([
                    'role_ids' => ['Vous ne pouvez pas modifier vos propres rôles.'],
                ]);
            }

            $previousRoles = $lockedTarget->roles()->pluck('name')->sort()->values()->all();
            $keepsAdministrator = Role::query()
                ->whereIn('id', $roleIds)
                ->where('name', 'admin')
                ->exists();

            $targetIsActiveAdministrator = $lockedTarget->hasRole('admin') && $lockedTarget->isActive();
            $activeAdministratorCount = $administrators
                ->filter(fn (User $administrator) => $administrator->isActive())
                ->count();

            if ($targetIsActiveAdministrator && ! $keepsAdministrator && $activeAdministratorCount <= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => ['Le dernier compte administrateur actif ne peut pas perdre son rôle.'],
                ]);
            }

            $lockedTarget->roles()->sync($roleIds);

            if ($lockedTarget->hasRole('professional') && $lockedTarget->professionalProfile()->exists() === false) {
                $professionalProfile = $lockedTarget->professionalProfile()->create();
                $professionalProfile->forceFill([
                    'status' => 'draft',
                    'visibility' => 'private',
                    'verification_status' => ProfessionalVerificationStatus::PENDING,
                    'professional_terms_accepted_at' => now(),
                ])->save();
            }

            $this->sessionRevocationService->revokeAll($lockedTarget);

            $newRoles = $lockedTarget->roles()->pluck('name')->sort()->values()->all();
            $this->auditLogService->record(
                'admin.user.roles_updated',
                $lockedTarget,
                $actor,
                ['previous_roles' => $previousRoles, 'new_roles' => $newRoles],
                $request,
            );

            return $lockedTarget->fresh(['roles.permissions']);
        });
    }
}
