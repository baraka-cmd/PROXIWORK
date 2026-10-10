<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Enums\CategoryStatus;
use App\Enums\SkillStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $professional = $this->input('account_type') === 'professional';

        return [
            'account_type' => ['required', 'string', Rule::in(['client', 'professional'])],
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\s-]{7,30}$/'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'terms' => ['accepted'],

            'business_name' => $professional ? ['nullable', 'string', 'max:160'] : ['prohibited'],
            'city' => $professional ? ['required', 'string', 'max:100'] : ['prohibited'],
            'province' => $professional ? ['nullable', 'string', 'max:100'] : ['prohibited'],
            'commune' => $professional ? ['nullable', 'string', 'max:100'] : ['prohibited'],

            'category_ids' => $professional ? ['required', 'array', 'min:1', 'max:2'] : ['prohibited'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'skill_ids' => $professional ? ['required', 'array', 'min:1'] : ['prohibited'],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],

            'services' => $professional ? ['required', 'array', 'min:1', 'max:10'] : ['prohibited'],
            'services.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'services.*.title' => ['required', 'string', 'min:3', 'max:160'],
            'services.*.description' => ['required', 'string', 'min:10', 'max:5000'],
            'services.*.skill_ids' => ['required', 'array', 'min:1'],
            'services.*.skill_ids.*' => ['required', 'integer', 'distinct', 'exists:skills,id'],
            'services.*.pricing_type' => ['required', Rule::in(['fixed', 'from', 'range', 'quote'])],
            'services.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'services.*.price_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'services.*.price_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'services.*.currency' => ['nullable', Rule::in(['CDF', 'USD'])],
            'services.*.billing_unit' => ['nullable', 'string', 'max:50'],
            'services.*.estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'services.*.service_area' => ['nullable', 'string', 'max:255'],
            'services.*.conditions' => ['nullable', 'string', 'max:3000'],
            'services.*.images' => ['nullable', 'array', 'max:4'],
            'services.*.images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            'documents' => $professional ? ['nullable', 'array', 'max:8'] : ['prohibited'],
            'documents.*.type' => ['nullable', 'required_with:documents.*.file', Rule::in(['identity', 'certificate', 'diploma', 'license', 'reference', 'other'])],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('account_type') !== 'professional') {
                return;
            }

            $categoryIds = collect($this->input('category_ids', []))->map(fn ($id) => (int) $id)->unique()->values();
            $skillIds = collect($this->input('skill_ids', []))->map(fn ($id) => (int) $id)->unique()->values();

            $activeCategories = DB::table('categories')
                ->whereIn('id', $categoryIds)
                ->where('status', CategoryStatus::ACTIVE->value)
                ->pluck('id');

            if ($activeCategories->count() !== $categoryIds->count()) {
                $validator->errors()->add('category_ids', 'Choisissez au maximum deux catégories principales actuellement actives.');
            }

            $activeSkills = DB::table('skills')
                ->whereIn('id', $skillIds)
                ->where('status', SkillStatus::ACTIVE->value)
                ->pluck('id');

            if ($activeSkills->count() !== $skillIds->count()) {
                $validator->errors()->add('skill_ids', 'Une ou plusieurs compétences ne sont plus disponibles.');
            }

            if ($categoryIds->isNotEmpty() && $skillIds->isNotEmpty()) {
                $validSkillIds = DB::table('category_skill')
                    ->whereIn('category_id', $categoryIds)
                    ->whereIn('skill_id', $skillIds)
                    ->distinct()
                    ->pluck('skill_id');

                if ($validSkillIds->count() !== $skillIds->count()) {
                    $validator->errors()->add('skill_ids', 'Chaque compétence doit appartenir à une catégorie sélectionnée.');
                }
            }

            foreach ($this->input('services', []) as $index => $service) {
                $pricingType = $service['pricing_type'] ?? 'quote';
                if (in_array($pricingType, ['fixed', 'from'], true) && (! isset($service['price']) || $service['price'] === '')) {
                    $validator->errors()->add("services.$index.price", 'Indiquez le tarif demandé pour ce mode de tarification.');
                }
                if ($pricingType === 'range') {
                    if (! isset($service['price_min']) || $service['price_min'] === '' || ! isset($service['price_max']) || $service['price_max'] === '') {
                        $validator->errors()->add("services.$index.price_min", 'Indiquez le tarif minimum et maximum.');
                    } elseif ((float) $service['price_max'] < (float) $service['price_min']) {
                        $validator->errors()->add("services.$index.price_max", 'Le tarif maximum doit être supérieur ou égal au tarif minimum.');
                    }
                }

                $serviceCategoryId = (int) ($service['category_id'] ?? 0);
                if ($categoryIds->contains($serviceCategoryId) === false) {
                    $validator->errors()->add("services.$index.category_id", 'Le service doit appartenir à une catégorie choisie.');
                }

                $serviceSkillIds = collect($service['skill_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
                if ($serviceSkillIds->isEmpty() || $serviceSkillIds->diff($skillIds)->isNotEmpty()) {
                    $validator->errors()->add("services.$index.skill_ids", 'Les compétences du service doivent être sélectionnées dans votre profil.');
                    continue;
                }

                $relatedSkills = DB::table('category_skill')
                    ->where('category_id', $serviceCategoryId)
                    ->whereIn('skill_id', $serviceSkillIds)
                    ->distinct()
                    ->count('skill_id');

                if ($relatedSkills !== $serviceSkillIds->count()) {
                    $validator->errors()->add("services.$index.skill_ids", 'Les compétences choisies ne correspondent pas à la catégorie de ce service.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'email' => 'adresse e-mail',
            'phone' => 'numéro de téléphone',
            'account_type' => 'type de compte',
            'category_ids' => 'catégories',
            'skill_ids' => 'compétences',
            'services' => 'services',
            'password' => 'mot de passe',
            'terms' => 'conditions d’utilisation',
        ];
    }

    public function messages(): array
    {
        return [
            'account_type.in' => 'Choisissez un type de compte autorisé.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'terms.accepted' => 'Vous devez accepter les conditions d’utilisation et la politique de confidentialité.',
            'category_ids.max' => 'Vous pouvez sélectionner deux catégories principales au maximum.',
            'services.min' => 'Ajoutez au moins un service pour présenter votre activité.',
            'documents.*.file.mimes' => 'Un document doit être un PDF ou une image JPG, PNG ou WebP.',
        ];
    }
}
