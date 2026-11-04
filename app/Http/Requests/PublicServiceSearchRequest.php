<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ServicePricingType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:180', Rule::exists('categories', 'slug')->where('status', 'active')],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:180', 'distinct', Rule::exists('skills', 'slug')->where('status', 'active')],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'required_with:min_price,max_price', 'required_if:sort,price_low,price_high', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'pricing_type' => ['nullable', Rule::enum(ServicePricingType::class)],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'availability' => ['nullable', Rule::enum(ProfessionalAvailabilityStatus::class)],
            'sort' => ['sometimes', Rule::in(['relevance', 'rating', 'price_low', 'price_high', 'newest'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (
                $this->filled('min_price')
                && $this->filled('max_price')
                && (float) $this->input('min_price') > (float) $this->input('max_price')
            ) {
                $validator->errors()->add('max_price', 'Le prix maximum doit être supérieur ou égal au prix minimum.');
            }

            $isPriceComparison = $this->filled('min_price')
                || $this->filled('max_price')
                || in_array($this->input('sort'), ['price_low', 'price_high'], true);

            if ($isPriceComparison && $this->filled('pricing_type') && $this->input('pricing_type') !== ServicePricingType::FIXED->value) {
                $validator->errors()->add('pricing_type', 'Le budget et le tri par prix s’appliquent aux tarifs fixes uniquement.');
            }
        }];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->isMethod('GET')) {
            $routeName = $this->routeIs('client.search') ? 'client.search' : 'public.search';

            throw new HttpResponseException(
                redirect()->route($routeName)->withErrors($validator)
            );
        }

        parent::failedValidation($validator);
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['search', 'category', 'city', 'province', 'currency', 'pricing_type', 'rating', 'availability'] as $field) {
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
