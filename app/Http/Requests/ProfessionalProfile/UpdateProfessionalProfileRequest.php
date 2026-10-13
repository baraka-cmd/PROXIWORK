<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalProfile;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfessionalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100', 'required_with:last_name'],
            'last_name' => ['nullable', 'string', 'max:100', 'required_with:first_name'],
            'professional_title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'languages_text' => ['nullable', 'string', 'max:1000'],
            'languages' => ['sometimes', 'array', 'max:12'],
            'languages.*' => ['string', 'max:60', 'distinct:ignore_case'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'starting_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'required_with:currency'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'required_with:starting_price'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'availability_status' => ['required', Rule::enum(ProfessionalAvailabilityStatus::class)],
            'visibility' => ['required', Rule::in([
                ProfessionalProfile::VISIBILITY_PUBLIC,
                ProfessionalProfile::VISIBILITY_PRIVATE,
            ])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('latitude') xor $this->filled('longitude')) {
                    $validator->errors()->add(
                        $this->filled('latitude') ? 'longitude' : 'latitude',
                        'Les coordonnées latitude et longitude doivent être fournies ensemble.',
                    );
                }

                if ($validator->errors()->isNotEmpty()
                    || $this->input('visibility') !== ProfessionalProfile::VISIBILITY_PUBLIC) {
                    return;
                }

                $user = $this->user();

                if (! $user instanceof User) {
                    return;
                }

                $professional = $user->professionalProfile()->first();

                // A profile that is already public may still be edited if an administrator
                // has since changed its eligibility. Public visibility is checked when
                // the professional explicitly switches a private profile to public.
                if ($professional === null
                    || $professional->visibility === ProfessionalProfile::VISIBILITY_PUBLIC) {
                    return;
                }

                $hasPublishedService = $professional->services()
                    ->where('status', ServiceStatus::PUBLISHED->value)
                    ->whereNotNull('published_at')
                    ->whereHas('category', fn ($query) => $query->where('status', 'active'))
                    ->exists();

                if ($professional->status !== ProfessionalProfile::STATUS_ACTIVE
                    || $professional->verification_status !== ProfessionalVerificationStatus::VERIFIED
                    || ! $hasPublishedService) {
                    $validator->errors()->add(
                        'visibility',
                        'La visibilité publique nécessite un profil actif et vérifié ainsi qu’au moins un service publié.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['first_name', 'last_name', 'professional_title', 'description', 'province', 'city', 'commune'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('currency') && $this->input('currency') !== null) {
            $data['currency'] = strtoupper(trim((string) $this->input('currency')));
        }

        if ($this->has('languages_text') && is_string($this->input('languages_text'))) {
            $languages = preg_split('/[,;\\r\\n]+/u', $this->input('languages_text')) ?: [];
            $languages = collect($languages)
                ->map(fn (string $language): string => trim($language))
                ->filter()
                ->unique(fn (string $language): string => mb_strtolower($language))
                ->values()
                ->all();

            $data['languages'] = $languages;
        }

        $this->merge($data);
    }
}
