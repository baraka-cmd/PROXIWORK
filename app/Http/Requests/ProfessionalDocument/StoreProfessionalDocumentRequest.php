<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalDocument;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfessionalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['identity', 'certificate', 'diploma', 'license', 'reference', 'other'])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
