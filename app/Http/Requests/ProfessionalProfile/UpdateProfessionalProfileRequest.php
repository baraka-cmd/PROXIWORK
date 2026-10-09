<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalProfile;

use Illuminate\Foundation\Http\FormRequest;
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
            'professional_title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'starting_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'required_with:currency'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'required_with:starting_price'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
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
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['professional_title', 'description', 'province', 'city', 'commune'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('currency') && $this->input('currency') !== null) {
            $data['currency'] = strtoupper(trim((string) $this->input('currency')));
        }

        $this->merge($data);
    }
}
