<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email_verified')) {
            $value = $this->input('email_verified');

            if (is_string($value)) {
                $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($normalized !== null) {
                    $this->merge(['email_verified' => $normalized]);
                }
            }
        }
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
