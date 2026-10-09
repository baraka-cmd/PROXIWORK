<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Verification;

use Illuminate\Foundation\Http\FormRequest;

class StartProfessionalVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('professional')) ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
