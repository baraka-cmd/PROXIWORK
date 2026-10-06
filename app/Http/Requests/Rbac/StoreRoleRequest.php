<?php

declare(strict_types=1);

namespace App\Http\Requests\Rbac;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
}
