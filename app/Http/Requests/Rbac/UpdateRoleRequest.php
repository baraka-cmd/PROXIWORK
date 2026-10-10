<?php

namespace App\Http\Requests\Rbac;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && $this->user()?->can('update', $role);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('permission_ids') || $this->user() === null) {
                return;
            }

            $permissionIds = collect($this->input('permission_ids', []))
                ->map(fn ($id) => (int) $id)
                ->unique();

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
        $role = $this->route('role');

        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
        ];
    }
}
