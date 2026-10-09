@extends('layouts.auth')

@section('title', 'Réinitialiser le mot de passe — PROXIWORK')
@section('meta_description', 'Choisissez un nouveau mot de passe sécurisé pour votre compte PROXIWORK.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/login.css')
    @endunless
@endpush

@section('auth_content')
    <div class="login-page">
        <section class="login-card" aria-labelledby="password-reset-title">
            <div class="login-card__header">
                <span class="login-eyebrow"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> SÉCURITÉ DU COMPTE</span>
                <h1 id="password-reset-title">Créer un nouveau mot de passe</h1>
                <p>Choisissez un mot de passe d’au moins 8 caractères avec majuscules, minuscules, chiffres et symbole.</p>
            </div>

            @if ($errors->any())
                <div class="login-alert" role="alert" tabindex="-1">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div><strong>Réinitialisation impossible</strong><p>{{ $errors->first() }}</p></div>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="login-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="login-field">
                    <label for="email">Adresse e-mail</label>
                    <div class="login-input">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="{{ old('email', request()->query('email')) }}" autocomplete="email" required>
                    </div>
                    @error('email') <p class="login-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="login-field">
                    <label for="password">Nouveau mot de passe</label>
                    <div class="login-input">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    @error('password') <p class="login-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="login-field">
                    <label for="password_confirmation">Confirmer le mot de passe</label>
                    <div class="login-input">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                </div>
                <button type="submit" class="login-submit">Réinitialiser le mot de passe</button>
            </form>
        </section>
    </div>
@endsection
