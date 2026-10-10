<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Enums\ProfessionalVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\SyncUserRolesRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SessionRevocationService $sessionRevocationService,
        private readonly DatabaseManager $database,
    ) {}

    public function update(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor !== null, 401);
        abort_if($actor->is($user), 403, 'Vous ne pouvez pas modifier vos propres rôles.');

        $roleIds = collect($request->validated('role_ids'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $updated = $this->database->transaction(function () use ($user, $actor, $roleIds, $request): User {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $previousRoles = $target->roles()->pluck('name')->sort()->values()->all();

            $keepsAdministrator = Role::query()
                ->whereIn('id', $roleIds)
                ->where('name', 'admin')
                ->exists();

            $targetIsAdministrator = $target->hasRole('admin');
            $administratorCount = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                ->count();

            if ($targetIsAdministrator && ! $keepsAdministrator && $administratorCount <= 1) {
                throw ValidationException::withMessages([
                    'role_ids' => ['Le dernier compte administrateur ne peut pas perdre son rôle.'],
                ]);
            }

            $target->roles()->sync($roleIds);

            if ($target->hasRole('professional') && $target->professionalProfile()->exists() === false) {
                $target->professionalProfile()->create([
                    'status' => 'draft',
                    'visibility' => 'private',
                    'verification_status' => ProfessionalVerificationStatus::PENDING,
                    'professional_terms_accepted_at' => now(),
                ]);
            }

            $this->sessionRevocationService->revokeAll($target);

            $newRoles = $target->roles()->pluck('name')->sort()->values()->all();
            $this->auditLogService->record(
                'admin.user.roles_updated',
                $target,
                $actor,
                ['previous_roles' => $previousRoles, 'new_roles' => $newRoles],
                $request,
            );

            return $target->fresh(['roles.permissions']);
        });

        return response()->json([
            'message' => 'Les rôles de l’utilisateur ont été mis à jour. Ses anciennes sessions et clés API ont été révoquées.',
            'data' => [
                'user_id' => $updated->getKey(),
                'roles' => $updated->roles->map(fn (Role $role): array => [
                    'id' => $role->getKey(),
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'permissions' => $role->permissions->pluck('name')->values(),
                ])->values(),
            ],
            'meta' => [],
        ]);
    }
}
