<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalDocument;

use App\Models\ProfessionalDocument;
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
            'document_type' => [
                'required',
                Rule::in([
                    ProfessionalDocument::TYPE_IDENTITY,
                    ProfessionalDocument::TYPE_CERTIFICATE,
                    ProfessionalDocument::TYPE_TRAINING,
                    ProfessionalDocument::TYPE_LICENSE,
                    ProfessionalDocument::TYPE_REFERENCE,
                    ProfessionalDocument::TYPE_OTHER,
                ]),
            ],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}