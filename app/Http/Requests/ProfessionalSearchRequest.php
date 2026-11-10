<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'profession' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:180', Rule::exists('categories', 'slug')->where('status', 'active')],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:180', 'distinct', Rule::exists('skills', 'slug')],
            'skills_mode' => ['sometimes', Rule::in(['any', 'all'])],
            'skill' => ['nullable', 'string', 'max:180', Rule::exists('skills', 'slug')->where('status', 'active')],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'required_with:min_price,max_price', 'required_if:sort,price_low,price_high', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'availability' => ['nullable', Rule::enum(ProfessionalAvailabilityStatus::class)],
            'verification' => ['nullable', Rule::enum(ProfessionalVerificationStatus::class)],
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
            },
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->isMethod('GET') && ! $this->expectsJson()) {
            $routeName = $this->routeIs('client.professionals.index')
                ? 'client.professionals.index'
                : 'public.professionals.index';

            throw new HttpResponseException(
                redirect()->route($routeName)->withErrors($validator)
            );
        }

        parent::failedValidation($validator);
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['profession', 'search', 'category', 'skill', 'city', 'province', 'currency'] as $field) {
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
