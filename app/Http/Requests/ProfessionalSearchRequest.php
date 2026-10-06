<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfessionalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'skill_ids' => ['sometimes', 'array', 'max:20'],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
            'skills_mode' => ['sometimes', Rule::in(['any', 'all'])],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:min_price'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
            'sort' => ['sometimes', Rule::in(['relevance', 'price_low', 'price_high', 'newest'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['search', 'city', 'province'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }

        foreach (['country_code', 'currency'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = strtoupper(trim((string) $this->input($field)));
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }
}
