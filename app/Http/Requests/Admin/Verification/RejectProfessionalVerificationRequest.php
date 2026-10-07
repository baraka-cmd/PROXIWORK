<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Verification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectProfessionalVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('professional')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::in([
                'DOCUMENT_INVALID',
                'PROFILE_INCOMPLETE',
                'IDENTITY_MISMATCH',
                'OTHER',
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
