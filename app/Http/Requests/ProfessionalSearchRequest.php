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
            'profession' => ['sometimes', 'nullable', 'string', 'max:120'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category' => ['sometimes', 'nullable', 'string', 'max:180', Rule::exists('categories', 'slug')->where('status', 'active')],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:180', 'exists:skills,slug'],
            'skills_mode' => ['sometimes', Rule::in(['any', 'all'])],
            'skill' => ['sometimes', 'nullable', 'string', 'max:180', 'exists:skills,slug'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'billing_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5'],
            'availability' => ['sometimes', 'nullable', Rule::enum(ProfessionalAvailabilityStatus::class)],
            'verification' => ['sometimes', 'nullable', Rule::in(['verified'])],
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

                $needsComparablePrice = $this->filled('min_price')
                    || $this->filled('max_price')
                    || in_array($this->input('sort'), ['price_low', 'price_high'], true);

                if ($needsComparablePrice && ! $this->filled('currency')) {
                    $validator->errors()->add(
                        'currency',
                        'Choisissez une devise pour comparer des prix équivalents.'
                    );
                }

                if ($needsComparablePrice && ! $this->filled('billing_unit')) {
                    $validator->errors()->add(
                        'billing_unit',
                        'Choisissez une unité de facturation pour comparer des tarifs équivalents.'
                    );
                }

                if (in_array($this->input('sort'), ['price_low', 'price_high'], true)
                    && (! $this->filled('currency') || ! $this->filled('billing_unit'))) {
                    $validator->errors()->add(
                        'sort',
                        'Choisissez une devise et une unité de facturation dans le filtre Budget pour trier des prix comparables.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['profession', 'search', 'category', 'skill', 'city', 'province', 'currency', 'billing_unit'] as $field) {
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
