<?php

declare(strict_types=1);

namespace App\Http\Requests\Rbac;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('rbac.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1', 'max:20'],
            'role_ids.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $actor = $this->user();

            if ($actor === null) {
                return;
            }

            $roleIds = collect($this->input('role_ids', []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($roleIds->isEmpty()) {
                return;
            }

            $availablePermissions = $actor->roles()
                ->with('permissions:id')
                ->get()
                ->flatMap(fn (Role $role) => $role->permissions->pluck('id'))
                ->unique();

            $requestedRoles = Role::query()
                ->whereIn('id', $roleIds)
                ->with('permissions:id,name')
                ->get();

            $requestedPermissionIds = $requestedRoles
                ->flatMap(fn (Role $role) => $role->permissions->pluck('id'))
                ->unique();

            if ($requestedPermissionIds->diff($availablePermissions)->isNotEmpty()) {
                $validator->errors()->add(
                    'role_ids',
                    'Vous ne pouvez attribuer que des rôles dont toutes les permissions vous sont déjà accordées.'
                );
            }

            if ($requestedRoles->contains(fn (Role $role) => $role->name === 'admin')
                && ! $actor->hasPermissionTo('admin.dashboard.view')) {
                $validator->errors()->add(
                    'role_ids',
                    'Seul un administrateur autorisé peut attribuer le rôle administrateur.'
                );
            }
        });
    }
}
