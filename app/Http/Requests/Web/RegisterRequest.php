<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom complet',
            'email' => 'adresse e-mail',
            'password' => 'mot de passe',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Votre nom complet est obligatoire.',
            'name.min' => 'Votre nom complet doit contenir au moins :min caractères.',
            'email.required' => 'Votre adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ];
    }
}
