<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
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
            'search' => ['nullable', 'string', 'max:160'],
            'role' => ['nullable', 'string', 'max:50', Rule::exists('roles', 'name')],
            'account_status' => ['nullable', Rule::enum(UserAccountStatus::class)],
            'email_verified' => ['nullable', 'boolean'],
            'period' => ['nullable', Rule::in(['today', '7d', '30d'])],
            'location' => ['nullable', 'string', 'max:120'],
            'verification_status' => ['nullable', Rule::enum(ProfessionalVerificationStatus::class)],
            'activity' => ['nullable', Rule::in(['recent_7d', 'inactive_30d'])],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['created_at', '-created_at', 'name', '-name'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
