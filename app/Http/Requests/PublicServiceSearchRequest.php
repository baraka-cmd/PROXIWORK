<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\CategoryStatus;
use App\Enums\SkillStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicServiceSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                Rule::exists('categories', 'slug')->where('status', CategoryStatus::ACTIVE->value),
            ],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => [
                'string',
                'distinct',
                Rule::exists('skills', 'slug')->where('status', SkillStatus::ACTIVE->value),
            ],
            'skills_mode' => ['sometimes', Rule::in(['any', 'all'])],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5'],
            'availability' => ['sometimes', 'nullable', Rule::enum(ProfessionalAvailabilityStatus::class)],
            'verified_only' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['relevance', 'rating', 'price_low', 'price_high', 'newest'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $min = $this->input('min_price');
                $max = $this->input('max_price');
                $sort = $this->input('sort', 'relevance');

                if ($min !== null && $max !== null && (float) $min > (float) $max) {
                    $validator->errors()->add(
                        'max_price',
                        'Le prix maximum doit être supérieur ou égal au prix minimum.'
                    );
                }

                if (($min !== null || $max !== null || in_array($sort, ['price_low', 'price_high'], true))
                    && ! $this->filled('currency')) {
                    $validator->errors()->add(
                        'currency',
                        'Choisissez une devise pour filtrer ou comparer les tarifs.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['search', 'profession', 'category', 'city', 'province', 'currency'] as $field) {
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

        if ($this->has('verified_only')) {
            $data['verified_only'] = filter_var(
                $this->input('verified_only'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
        }

        $this->merge($data);
    }
}
