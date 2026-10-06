<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile instanceof Profile
            && $this->user()?->can('update', $profile);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'min:1', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'min:1', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'locale' => ['sometimes', 'required', 'string', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'timezone' => ['sometimes', 'required', 'timezone', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->input('first_name') !== null ? trim((string) $this->input('first_name')) : null,
            'last_name' => $this->input('last_name') !== null ? trim((string) $this->input('last_name')) : null,
            'phone' => $this->input('phone') !== null ? trim((string) $this->input('phone')) : null,
            'bio' => $this->input('bio') !== null ? trim((string) $this->input('bio')) : null,
            'locale' => $this->input('locale') !== null ? trim((string) $this->input('locale')) : null,
            'timezone' => $this->input('timezone') !== null ? trim((string) $this->input('timezone')) : null,
        ]);
    }
}
