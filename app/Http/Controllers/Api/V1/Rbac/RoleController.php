<?php

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\StoreRoleRequest;
use App\Http\Requests\Rbac\UpdateRoleRequest;
use App\Models\Permission;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): JsonResource
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('display_name')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return RoleResource::collection($roles)->additional(['message' => 'Rôles récupérés avec succès.']);
    }

    public function show(Role $role): JsonResource
    {
        $this->authorize('view', $role);

        $role->load(['permissions:id,name,display_name,group', 'users:id,name,email']);

        return (new RoleResource($role))->additional(['message' => 'Rôle récupéré avec succès.', 'meta' => []]);
    }

    public function store(StoreRoleRequest $request): JsonResource|JsonResponse
    {
        $role = DB::transaction(function () use ($request): Role {
            $role = Role::create($request->safe()->only(['name', 'display_name', 'description']));

            $permissionIds = $request->validated('permission_ids', []);
            $role->permissions()->sync($permissionIds);

            return $role->load('permissions');
        });

        return (new RoleResource($role))->additional(['message' => 'Rôle créé avec succès.', 'meta' => []])->response()->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResource
    {
        $this->authorize('update', $role);

        $role = DB::transaction(function () use ($request, $role): Role {
            $role->update($request->safe()->only(['name', 'display_name', 'description']));

            if ($request->has('permission_ids')) {
                $role->permissions()->sync($request->validated('permission_ids'));
            }

            return $role->load('permissions');
        });

        return (new RoleResource($role))->additional(['message' => 'Rôle mis à jour avec succès.', 'meta' => []]);
    }

    public function destroy(Role $role): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('delete', $role);

        $role->delete();

        return response()->noContent();
    }

    public function permissions(): JsonResource
    {
        $this->authorize('viewAny', Role::class);

        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'group', 'description']);

        return PermissionResource::collection($permissions)->additional(['message' => 'Permissions récupérées avec succès.']);
    }
}
