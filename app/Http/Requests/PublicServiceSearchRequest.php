<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicServiceSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', 'string', 'max:180', 'exists:categories,slug'],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:180', 'distinct', 'exists:skills,slug'],
            'city' => ['sometimes', 'string', 'max:120'],
            'province' => ['sometimes', 'string', 'max:120'],
            'min_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'sort' => ['sometimes', Rule::in(['relevance', 'price_low', 'price_high', 'newest'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['search', 'category', 'city', 'province', 'currency'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (array_key_exists('skills', $this->all())) {
            $data['skills'] = collect($this->input('skills', []))
                ->map(fn ($skill): string => trim((string) $skill))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $this->merge($data);
    }
}