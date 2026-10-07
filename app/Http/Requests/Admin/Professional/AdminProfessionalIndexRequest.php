<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Professional;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminProfessionalIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\ProfessionalProfile::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:160'],
            'verification_status' => ['sometimes', Rule::enum(ProfessionalVerificationStatus::class)],
            'account_status' => ['sometimes', Rule::enum(UserAccountStatus::class)],
            'availability_status' => ['sometimes', 'string', 'max:20'],
            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date', 'after_or_equal:created_from'],
            'sort' => ['sometimes', Rule::in(['created_at', '-created_at', 'rating', '-rating'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
