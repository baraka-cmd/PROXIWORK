<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\SyncUserRolesRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\UserRoleAssignmentService;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    public function __construct(
        private readonly UserRoleAssignmentService $roleAssignmentService,
    ) {}

    public function update(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor !== null, 401);
        abort_if($actor->is($user), 403, 'Vous ne pouvez pas modifier vos propres rôles.');

        $updated = $this->roleAssignmentService->sync(
            $user,
            $actor,
            collect($request->validated('role_ids'))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            $request,
        );

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
