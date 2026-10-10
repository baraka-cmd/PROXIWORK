<?php

declare(strict_types=1);

namespace App\Services\Admin\Role;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRoleService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(int $perPage = 20)
    {
        return Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('is_system', 'desc')
            ->orderBy('display_name')
            ->paginate(max(1, min($perPage, 100)))
            ->withQueryString();
    }

    public function find(Role $role): Role
    {
        return $role->load([
            'permissions:id,name,display_name,group,description',
        ])->loadCount('users');
    }

    public function permissions(User $actor)
    {
        $allowedPermissionIds = $actor->roles()
            ->with('permissions:id')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('id'))
            ->unique();

        return Permission::query()
            ->whereIn('id', $allowedPermissionIds)
            ->orderBy('group')
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'group', 'description'])
            ->groupBy('group');
    }

    public function create(array $data, Request $request): Role
    {
        return DB::transaction(function () use ($data, $request): Role {
            $role = Role::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            $role->permissions()->sync($data['permission_ids'] ?? []);

            $this->auditLogService->record(
                'role_created',
                $role,
                $request->user(),
                ['permission_ids' => $data['permission_ids'] ?? []],
                $request,
            );

            return $role->load('permissions');
        });
    }

    public function update(Role $role, array $data, Request $request): Role
    {
        return DB::transaction(function () use ($role, $data, $request): Role {
            $role->update([
                'name' => $data['name'] ?? $role->name,
                'display_name' => $data['display_name'] ?? $role->display_name,
                'description' => $data['description'] ?? null,
            ]);

            if (array_key_exists('permission_ids', $data)) {
                $actorPermissionIds = $request->user()->roles()
                    ->with('permissions:id')
                    ->get()
                    ->flatMap(fn ($assignedRole) => $assignedRole->permissions->pluck('id'))
                    ->unique();

                $protectedExistingIds = $role->permissions()
                    ->pluck('permissions.id')
                    ->diff($actorPermissionIds);

                $permissionIds = collect($data['permission_ids'])
                    ->merge($protectedExistingIds)
                    ->unique()
                    ->values()
                    ->all();

                $role->permissions()->sync($permissionIds);
            }

            $this->auditLogService->record(
                'role_updated',
                $role,
                $request->user(),
                ['permission_ids_changed' => array_key_exists('permission_ids', $data)],
                $request,
            );

            return $role->load('permissions');
        });
    }

    public function delete(Role $role, Request $request): void
    {
        DB::transaction(function () use ($role, $request): void {
            $role->permissions()->detach();
            $role->users()->detach();

            $this->auditLogService->record(
                'role_deleted',
                $role,
                $request->user(),
                ['role_name' => $role->name],
                $request,
            );

            $role->delete();
        });
    }
}
