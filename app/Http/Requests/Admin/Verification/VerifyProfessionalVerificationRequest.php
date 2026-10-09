<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Verification;

use Illuminate\Foundation\Http\FormRequest;

class VerifyProfessionalVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verify', $this->route('professional')) ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
