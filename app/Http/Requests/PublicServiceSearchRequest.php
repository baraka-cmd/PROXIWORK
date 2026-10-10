<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ServicePricingType;
use App\Enums\SkillStatus;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'pricing_type' => ['sometimes', 'nullable', Rule::enum(ServicePricingType::class)],
            'billing_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5'],
            'availability' => ['sometimes', 'nullable', Rule::enum(ProfessionalAvailabilityStatus::class)],
            'verified_only' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['relevance', 'rating', 'price_low', 'price_high', 'newest'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $min = $this->input('min_price');
                $max = $this->input('max_price');
                $sort = $this->input('sort', 'relevance');
                $hasMin = is_scalar($min) && $min !== '';
                $hasMax = is_scalar($max) && $max !== '';
                $needsComparablePrice = $hasMin
                    || $hasMax
                    || in_array($sort, ['price_low', 'price_high'], true);

                if ($hasMin && $hasMax && is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
                    $validator->errors()->add(
                        'max_price',
                        'Le prix maximum doit être supérieur ou égal au prix minimum.'
                    );
                }

                if ($needsComparablePrice && !$this->filled('currency')) {
                    $validator->errors()->add(
                        'currency',
                        'Choisissez une devise pour filtrer ou comparer les tarifs.'
                    );
                }

                if ($this->filled('currency') && is_scalar($this->input('currency')) && !Service::query()
                    ->publiclyVisible()
                    ->where('currency', $this->input('currency'))
                    ->whereIn('pricing_type', [
                        ServicePricingType::FIXED->value,
                        ServicePricingType::FROM->value,
                        ServicePricingType::RANGE->value,
                    ])
                    ->exists()) {
                    $validator->errors()->add(
                        'currency',
                        'Choisissez une devise réellement proposée par un service public tarifé.'
                    );
                }

                if ($needsComparablePrice && !$this->filled('billing_unit')) {
                    $validator->errors()->add(
                        'billing_unit',
                        'Choisissez une unité de facturation pour comparer des tarifs équivalents.'
                    );
                }

                if ($this->filled('billing_unit') && is_scalar($this->input('billing_unit')) && !Service::query()
                    ->publiclyVisible()
                    ->whereNotNull('currency')
                    ->where('currency', '!=', '')
                    ->where('billing_unit', $this->input('billing_unit'))
                    ->whereIn('pricing_type', [
                        ServicePricingType::FIXED->value,
                        ServicePricingType::FROM->value,
                        ServicePricingType::RANGE->value,
                    ])
                    ->exists()) {
                    $validator->errors()->add(
                        'billing_unit',
                        'Choisissez une unité de facturation réellement proposée par un service public.'
                    );
                }
            },
        ];
    }

    protected function failedValidation(ValidatorContract $validator)
    {
        if ($this->isMethod('GET')) {
            $input = collect($this->query())->only([
                'search', 'profession', 'category', 'skills', 'skills_mode', 'city', 'province',
                'min_price', 'max_price', 'currency', 'pricing_type', 'billing_unit', 'rating', 'availability',
                'verified_only', 'sort', 'per_page', 'page',
            ])->all();

            foreach ([
                'search', 'profession', 'category', 'skills_mode', 'city', 'province',
                'min_price', 'max_price', 'currency', 'pricing_type', 'billing_unit', 'rating', 'availability',
                'verified_only', 'sort', 'per_page', 'page',
            ] as $field) {
                if (isset($input[$field]) && !is_scalar($input[$field])) {
                    unset($input[$field]);
                }
            }

            if (isset($input['skills'])) {
                if (!is_array($input['skills'])) {
                    unset($input['skills']);
                } else {
                    $input['skills'] = collect($input['skills'])
                        ->filter(fn ($skill): bool => is_scalar($skill))
                        ->map(fn ($skill): string => (string) $skill)
                        ->values()
                        ->all();
                }
            }

            throw new HttpResponseException(
                redirect()->route('public.search')->withErrors($validator)->withInput($input)
            );
        }

        parent::failedValidation($validator);
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['search', 'profession', 'category', 'city', 'province', 'currency', 'billing_unit'] as $field) {
            $value = $this->input($field);

            if ($this->has($field) && $value !== null && is_scalar($value)) {
                $data[$field] = trim((string) $value);
            }
        }

        foreach (['search', 'profession'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = preg_replace('/\s+/u', ' ', $data[$field]) ?? $data[$field];
            }
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $skillsInput = $this->input('skills');
        if (is_array($skillsInput)) {
            $normalizedSkills = [];

            foreach ($skillsInput as $skill) {
                if (is_scalar($skill)) {
                    $skill = trim((string) $skill);
                    if ($skill !== '') {
                        $normalizedSkills[] = $skill;
                    }
                } else {
                    $normalizedSkills[] = $skill;
                }
            }

            $data['skills'] = array_values($normalizedSkills);
        }

        $verifiedOnlyInput = $this->input('verified_only');
        if ($this->has('verified_only') && is_scalar($verifiedOnlyInput)) {
            $data['verified_only'] = filter_var(
                $verifiedOnlyInput,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
        }

        $this->merge($data);
    }
}
