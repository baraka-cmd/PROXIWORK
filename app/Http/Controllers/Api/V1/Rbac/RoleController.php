<?php

namespace App\Http\Controllers\Api\V1\Rbac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\StoreRoleRequest;
use App\Http\Requests\Rbac\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('display_name')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        $role->load(['permissions:id,name,display_name,group', 'users:id,name,email']);

        return response()->json(['success' => true, 'data' => $role]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request): Role {
            $role = Role::create($request->safe()->only(['name', 'display_name', 'description']));

            $permissionIds = $request->validated('permission_ids', []);
            $role->permissions()->sync($permissionIds);

            return $role->load('permissions');
        });

        return response()->json(['success' => true, 'message' => 'Role created.', 'data' => $role], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = DB::transaction(function () use ($request, $role): Role {
            $role->update($request->safe()->only(['name', 'display_name', 'description']));

            if ($request->has('permission_ids')) {
                $role->permissions()->sync($request->validated('permission_ids'));
            }

            return $role->load('permissions');
        });

        return response()->json(['success' => true, 'message' => 'Role updated.', 'data' => $role]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $role->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }

    public function permissions(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'group', 'description']);

        return response()->json(['success' => true, 'data' => $permissions]);
    }
}
