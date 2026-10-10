<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeEmailRequest extends FormRequest
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
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
                Rule::unique('users', 'pending_email')->ignore($userId),
            ],
            'current_password' => ['required', 'string', 'current_password:web'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'nouvelle adresse e-mail',
            'current_password' => 'mot de passe actuel',
        ];
    }
}
