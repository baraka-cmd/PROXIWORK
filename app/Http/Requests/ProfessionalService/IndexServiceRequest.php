<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalService;

use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ServiceStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'skill_id' => ['nullable', 'integer', 'exists:skills,id'],
            'sort' => ['nullable', Rule::in(['recent', 'title', 'requests'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge(['search' => trim((string) $this->input('search'))]);
        }
    }
}
