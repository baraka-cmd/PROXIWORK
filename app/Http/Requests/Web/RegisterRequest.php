<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Enums\ServiceExecutionMode;
use App\Enums\ServicePricingType;
use App\Enums\SkillStatus;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'phone' => trim((string) $this->input('phone')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function authorize(): bool
    {
        if ($this->routeIs('client.professional-profile.store')) {
            return $this->user()?->hasRole('client') === true
                && $this->user()?->professionalProfile()->doesntExist() === true;
        }

        return true;
    }

    public function rules(): array
    {
        $isProfessionalFlow = $this->routeIs('register.professional.store', 'client.professional-profile.store');
        $isExistingAccount = $this->routeIs('client.professional-profile.store');
        $rules = [
            'terms' => ['accepted'],
        ];

        if (! $isExistingAccount) {
            $rules = [
                ...$rules,
                'first_name' => ['required', 'string', 'min:2', 'max:100'],
                'last_name' => ['required', 'string', 'min:2', 'max:100'],
                'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,28}$/'],
                'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'confirmed', Password::min(12)],
            ];
        }

        if (! $isProfessionalFlow) {
            return [
                ...$rules,
                'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ];
        }

        $professionalRules = [
            'professional_title' => ['required', 'string', 'min:3', 'max:160'],
            'business_name' => ['nullable', 'string', 'max:160'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'service_radius_km' => ['required', 'integer', 'min:0', 'max:500'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'category_ids' => ['required', 'array', 'min:1', 'max:2'],
            'category_ids.*' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')->where('status', 'active')],
            'skill_ids' => ['required', 'array', 'min:1'],
            'skill_ids.*' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')->where('status', SkillStatus::ACTIVE->value)],
            'services' => ['required', 'array', 'min:1', 'max:8'],
            'services.*.category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', 'active')],
            'services.*.skill_ids' => ['required', 'array', 'min:1'],
            'services.*.skill_ids.*' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')->where('status', SkillStatus::ACTIVE->value)],
            'services.*.title' => ['required', 'string', 'min:3', 'max:160'],
            'services.*.description' => ['required', 'string', 'min:20', 'max:5000'],
            'services.*.pricing_type' => ['required', Rule::enum(ServicePricingType::class)],
            'services.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'services.*.price_min' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'services.*.price_max' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'services.*.currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'services.*.execution_mode' => ['required', Rule::enum(ServiceExecutionMode::class)],
            'services.*.estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*.type' => ['nullable', 'required_with:documents.*.file', Rule::in(['certificate', 'training', 'license', 'reference', 'other'])],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];

        if (! $isExistingAccount) {
            $professionalRules['profile_photo'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
        }

        return [...$rules, ...$professionalRules];
    }

    public function after(): array
    {
        if (! $this->routeIs('register.professional.store', 'client.professional-profile.store')) {
            return [];
        }

        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $selectedCategoryIds = collect($this->input('category_ids', []))->map(fn ($id): int => (int) $id);
            $activeRootCategoryIds = Category::query()
                ->active()
                ->root()
                ->whereIn('id', $selectedCategoryIds)
                ->pluck('id');

            if ($activeRootCategoryIds->count() !== $selectedCategoryIds->count()) {
                $validator->errors()->add('category_ids', 'Choisissez au maximum deux catégories principales actives.');

                return;
            }

            $selectedSkillIds = collect($this->input('skill_ids', []))->map(fn ($id): int => (int) $id);
            $eligibleSkillCategoryIds = Category::query()
                ->active()
                ->where(fn ($query) => $query
                    ->whereIn('id', $selectedCategoryIds)
                    ->orWhereIn('parent_id', $selectedCategoryIds))
                ->pluck('id');
            $selectedSkills = Skill::query()
                ->active()
                ->whereIn('id', $selectedSkillIds)
                ->whereIn('category_id', $eligibleSkillCategoryIds)
                ->get(['id', 'category_id']);

            if ($selectedSkills->count() !== $selectedSkillIds->count()) {
                $validator->errors()->add('skill_ids', 'Chaque compétence doit appartenir à une catégorie sélectionnée et être active.');

                return;
            }

            $skillCategoryRoots = Category::query()
                ->whereIn('id', $selectedSkills->pluck('category_id'))
                ->get(['id', 'parent_id'])
                ->mapWithKeys(fn (Category $category): array => [
                    $category->id => $category->parent_id ?? $category->id,
                ]);

            foreach ($selectedCategoryIds as $categoryId) {
                if (! $selectedSkills->contains(fn (Skill $skill): bool => (int) $skillCategoryRoots[$skill->category_id] === $categoryId)) {
                    $validator->errors()->add('skill_ids', 'Sélectionnez au moins une compétence dans chaque catégorie choisie.');

                    return;
                }
            }

            foreach ($this->input('services', []) as $index => $service) {
                $categoryId = (int) ($service['category_id'] ?? 0);
                $serviceSkillIds = collect($service['skill_ids'] ?? [])->map(fn ($id): int => (int) $id);

                if (! $selectedCategoryIds->contains($categoryId)) {
                    $validator->errors()->add("services.{$index}.category_id", 'Le service doit utiliser une catégorie de votre profil.');
                }

                $validServiceSkills = $selectedSkills
                    ->filter(fn (Skill $skill): bool => (int) $skillCategoryRoots[$skill->category_id] === $categoryId
                        && $serviceSkillIds->contains($skill->id))
                    ->pluck('id');

                if ($validServiceSkills->count() !== $serviceSkillIds->count()) {
                    $validator->errors()->add("services.{$index}.skill_ids", 'Les compétences du service doivent appartenir à sa catégorie et à votre profil.');
                }

                $pricingType = $service['pricing_type'] ?? null;
                if (in_array($pricingType, [ServicePricingType::FIXED->value, ServicePricingType::FROM->value], true)
                    && ! is_numeric($service['price'] ?? null)) {
                    $validator->errors()->add("services.{$index}.price", 'Indiquez le tarif de cette prestation.');
                }

                if ($pricingType === ServicePricingType::RANGE->value) {
                    if (! is_numeric($service['price_min'] ?? null) || ! is_numeric($service['price_max'] ?? null)) {
                        $validator->errors()->add("services.{$index}.price_min", 'Indiquez les deux bornes de la fourchette de prix.');
                    } elseif ((float) $service['price_min'] > (float) $service['price_max']) {
                        $validator->errors()->add("services.{$index}.price_max", 'Le tarif maximum doit être supérieur ou égal au tarif minimum.');
                    }
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'phone' => 'numéro de téléphone',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Votre prénom est obligatoire.',
            'first_name.min' => 'Votre prénom doit contenir au moins :min caractères.',
            'last_name.required' => 'Votre nom est obligatoire.',
            'last_name.min' => 'Votre nom doit contenir au moins :min caractères.',
            'phone.required' => 'Votre numéro de téléphone est obligatoire.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide.',
            'email.required' => 'Votre adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte.',
            'account_type.required' => 'Choisissez le type de compte à créer.',
            'account_type.in' => 'Le type de compte sélectionné n’est pas autorisé.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'terms.accepted' => 'Vous devez accepter les conditions d’utilisation pour créer votre compte.',
        ];
    }
}
