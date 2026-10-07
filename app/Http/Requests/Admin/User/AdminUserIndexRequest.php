<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Enums\UserAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:160'],
            'role' => ['sometimes', 'string', 'max:50'],
            'account_status' => ['sometimes', Rule::enum(UserAccountStatus::class)],
            'email_verified' => ['sometimes', 'boolean'],
            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date', 'after_or_equal:created_from'],
            'sort' => ['sometimes', Rule::in(['created_at', '-created_at', 'name', '-name'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
