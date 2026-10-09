<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalService;

use App\Enums\ServicePricingType;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('services.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'pricing_type' => ['required', Rule::enum(ServicePricingType::class)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'skill_ids' => ['sometimes', 'array', 'max:10'],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = Category::query()->find($this->integer('category_id'));
            if ($category !== null && $category->status->value !== 'active') {
                $validator->errors()->add('category_id', 'La catégorie doit être active.');
            }

            $this->validatePricing($validator);

            if ($this->filled('skill_ids')) {
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

        foreach (['title', 'short_description', 'description', 'currency'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $this->merge($data);
    }

    private function validatePricing(Validator $validator): void
    {
        $type = $this->input('pricing_type');

        if ($type === ServicePricingType::FIXED->value || $type === ServicePricingType::FROM->value) {
            if ($this->input('price') === null) {
                $validator->errors()->add('price', 'Le prix est obligatoire pour ce type de tarification.');
            }

            if ($this->input('price_min') !== null || $this->input('price_max') !== null) {
                $validator->errors()->add('price', 'Les bornes de prix ne sont pas autorisées avec ce type de tarification.');
            }
        }

        if ($type === ServicePricingType::RANGE->value) {
            if ($this->input('price_min') === null || $this->input('price_max') === null) {
                $validator->errors()->add('price_min', 'Les deux bornes de prix sont obligatoires.');
            } elseif ((float) $this->input('price_max') < (float) $this->input('price_min')) {
                $validator->errors()->add('price_max', 'Le prix maximum doit être supérieur ou égal au prix minimum.');
            }

            if ($this->input('price') !== null) {
                $validator->errors()->add('price', 'Le prix simple n’est pas autorisé avec une fourchette.');
            }
        }

        if ($type === ServicePricingType::QUOTE->value) {
            if ($this->input('price') !== null || $this->input('price_min') !== null || $this->input('price_max') !== null) {
                $validator->errors()->add('price', 'Aucun prix ne doit être fourni pour une tarification sur devis.');
            }
        }

        if ($type !== ServicePricingType::QUOTE->value && $this->input('currency') === null) {
            $validator->errors()->add('currency', 'La devise est obligatoire lorsqu’un prix est défini.');
        }
    }
}
