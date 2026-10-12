<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,28}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'account_type' => ['required', 'string', Rule::in(['client', 'professional'])],
            'terms' => ['accepted'],
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
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'phone' => 'numéro de téléphone',
            'email' => 'adresse e-mail',
            'account_type' => 'type de compte',
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
