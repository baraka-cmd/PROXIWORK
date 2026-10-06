<?php

declare(strict_types=1);

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('profiles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'recipient_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]{7,30}$/'],
            'country_code' => ['sometimes', 'required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'commune' => ['sometimes', 'nullable', 'string', 'max:100'],
            'neighborhood' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address_line_1' => ['sometimes', 'required', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'landmark' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'label', 'recipient_name', 'contact_phone', 'country_code',
            'province', 'city', 'commune', 'neighborhood', 'address_line_1',
            'address_line_2', 'landmark', 'postal_code',
        ];

        $normalized = [];

        foreach ($fields as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        if (array_key_exists('country_code', $normalized)) {
            $normalized['country_code'] = strtoupper($normalized['country_code']);
        }

        $this->merge($normalized);
    }
}
