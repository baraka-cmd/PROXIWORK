<?php

declare(strict_types=1);

namespace App\\Http\\Requests\\Web;

use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Validation\\Rule;
use Illuminate\\Validation\\Rules\\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name', 'phone', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'email' ? mb_strtolower($value) : $value]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'account_type' => ['required', 'string', Rule::in(['client', 'professional'])],
            'terms' => ['accepted'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
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
            'last_name.required' => 'Votre nom est obligatoire.',
            'email.required' => 'Votre adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte.',
            'account_type.required' => 'Choisissez le type de compte à créer.',
            'account_type.in' => 'Le type de compte sélectionné n’est pas autorisé.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'terms.accepted' => 'Vous devez accepter les conditions d’utilisation pour créer votre compte.',
        ];
    }
}
