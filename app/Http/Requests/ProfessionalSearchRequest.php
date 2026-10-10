<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProfessionalAvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfessionalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profession' => ['sometimes', 'string', 'max:120'],
            'search' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', 'string', 'max:180', Rule::exists('categories', 'slug')->where('status', 'active')],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:180', Rule::exists('skills', 'slug')->where('status', 'active')],
            'skills_mode' => ['sometimes', Rule::in(['any', 'all'])],
            'skill' => ['sometimes', 'string', 'max:180', Rule::exists('skills', 'slug')->where('status', 'active')],
            'city' => ['sometimes', 'string', 'max:120'],
            'province' => ['sometimes', 'string', 'max:120'],
            'min_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'availability' => ['sometimes', Rule::enum(ProfessionalAvailabilityStatus::class)],
            'verification' => ['sometimes', Rule::in(['verified'])],
            'sort' => ['sometimes', Rule::in(['relevance', 'rating', 'price_low', 'price_high', 'newest'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->filled('min_price')
                    && $this->filled('max_price')
                    && (float) $this->input('min_price') > (float) $this->input('max_price')
                ) {
                    $validator->errors()->add(
                        'max_price',
                        'Le prix maximum doit être supérieur ou égal au prix minimum.'
                    );
                }

                if (($this->filled('min_price') || $this->filled('max_price')) && ! $this->filled('currency')) {
                    $validator->errors()->add(
                        'currency',
                        'Choisissez une devise pour comparer des prix équivalents.'
                    );
                }

                if (in_array($this->input('sort'), ['price_low', 'price_high'], true) && ! $this->filled('currency')) {
                    $validator->errors()->add(
                        'sort',
                        'Choisissez une devise dans le filtre Budget pour trier des prix comparables.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['profession', 'search', 'category', 'skill', 'city', 'province', 'currency'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $value = trim((string) $this->input($field));
                $data[$field] = in_array($field, ['search', 'profession', 'city', 'province'], true)
                    ? preg_replace('/\s+/u', ' ', $value)
                    : $value;
            }
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (array_key_exists('skills', $this->all())) {
            $data['skills'] = collect($this->input('skills', []))
                ->map(fn ($skill): string => trim((string) $skill))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge($data);
    }

    public function skillSlugs(): array
    {
        return collect([
            ...($this->input('skills', []) ?: []),
            $this->input('skill'),
        ])->filter()->unique()->values()->all();
    }
}
