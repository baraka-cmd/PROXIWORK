<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') === true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'timezone', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name', '')),
            'last_name' => trim((string) $this->input('last_name', '')),
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'bio' => $this->filled('bio') ? trim((string) $this->input('bio')) : null,
        ]);
    }
}
