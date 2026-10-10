<?php

declare(strict_types=1);

namespace App\Http\Requests\Rbac;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $permissionIds = collect($this->input('permission_ids', []))
                ->map(fn ($id) => (int) $id)
                ->unique();

            if ($permissionIds->isEmpty() || $this->user() === null) {
                return;
            }

            $allowedPermissionIds = $this->user()->roles()
                ->with('permissions:id')
                ->get()
                ->flatMap(fn ($role) => $role->permissions->pluck('id'))
                ->unique();

            if ($permissionIds->diff($allowedPermissionIds)->isNotEmpty()) {
                $validator->errors()->add(
                    'permission_ids',
                    'Vous ne pouvez attribuer que des permissions que vous possédez déjà.'
                );
            }
        });
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions_submitted' => ['sometimes', 'accepted'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
}
