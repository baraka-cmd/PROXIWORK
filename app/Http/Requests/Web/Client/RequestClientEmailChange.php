<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestClientEmailChange extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') === true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                'different:current_email',
                Rule::unique('users', 'email'),
                Rule::unique('users', 'pending_email')->where(fn ($query) => $query->where('pending_email_expires_at', '>', now())),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'current_email' => mb_strtolower((string) $this->user()?->email),
        ]);
    }
}
