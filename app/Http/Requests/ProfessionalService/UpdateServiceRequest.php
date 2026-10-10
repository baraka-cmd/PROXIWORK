<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalService;

use App\Enums\ServicePricingType;
use App\Models\Category;
use App\Models\Service;
use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            && $this->user()?->hasPermissionTo('services.manage') === true
            && $service->professionalProfile->user_id === $this->user()->getKey();
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:160'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'required', 'string', 'min:20', 'max:10000'],
            'pricing_type' => ['sometimes', 'required', Rule::enum(ServicePricingType::class)],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_min' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'billing_unit' => ['sometimes', 'nullable', Rule::in(['package', 'hour', 'day', 'project'])],
            'service_area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:525600'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'skill_ids' => ['sometimes', 'array', 'max:10'],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $categoryId = $this->input('category_id', $this->route('service')->category_id);
            $category = Category::query()->find($categoryId);
            if ($category !== null && $category->status->value !== 'active') {
                $validator->errors()->add('category_id', 'La catégorie doit être active.');
            }

            $type = $this->input('pricing_type', $this->route('service')->pricing_type->value);
            $this->validatePricing($validator, $type);

            if ($this->has('skill_ids')) {
                $inactive = Skill::query()
                    ->whereIn('id', $this->input('skill_ids', []))
                    ->where('status', '!=', 'active')
                    ->exists();

                if ($inactive) {
                    $validator->errors()->add('skill_ids', 'Toutes les compétences sélectionnées doivent être actives.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['title', 'short_description', 'description', 'currency', 'service_area'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $service = $this->route('service');
        $pricingType = $this->input('pricing_type', $service->pricing_type->value);
        if (! $this->filled('billing_unit') && $pricingType !== ServicePricingType::QUOTE->value && $service->billing_unit === null) {
            $data['billing_unit'] = 'package';
        }

        $this->merge($data);
    }

    private function validatePricing(Validator $validator, string $type): void
    {
        $service = $this->route('service');

        $price = $this->has('price') ? $this->input('price') : $service->price;
        $min = $this->has('price_min') ? $this->input('price_min') : $service->price_min;
        $max = $this->has('price_max') ? $this->input('price_max') : $service->price_max;
        $currency = $this->has('currency') ? $this->input('currency') : $service->currency;

        if ($type === ServicePricingType::FIXED->value || $type === ServicePricingType::FROM->value) {
            if ($price === null) {
                $validator->errors()->add('price', 'Le prix est obligatoire pour ce type de tarification.');
            }
            if ($min !== null || $max !== null) {
                $validator->errors()->add('price_min', 'Les bornes de prix ne sont pas autorisées avec ce type de tarification.');
            }
        }

        if ($type === ServicePricingType::RANGE->value) {
            if ($min === null || $max === null) {
                $validator->errors()->add('price_min', 'Les deux bornes de prix sont obligatoires.');
            } elseif ((float) $max < (float) $min) {
                $validator->errors()->add('price_max', 'Le prix maximum doit être supérieur ou égal au prix minimum.');
            }
            if ($price !== null) {
                $validator->errors()->add('price', 'Le prix simple n’est pas autorisé avec une fourchette.');
            }
        }

        if ($type === ServicePricingType::QUOTE->value && ($price !== null || $min !== null || $max !== null)) {
            $validator->errors()->add('price', 'Aucun prix ne doit être fourni pour une tarification sur devis.');
        }

        if ($type !== ServicePricingType::QUOTE->value && $currency === null) {
            $validator->errors()->add('currency', 'La devise est obligatoire lorsqu’un prix est défini.');
        }
    }
}
