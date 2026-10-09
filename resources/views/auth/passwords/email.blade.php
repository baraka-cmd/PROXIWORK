@extends('layouts.auth')

@section('title', 'Mot de passe oublié — PROXIWORK')
@section('meta_description', 'Demandez un lien sécurisé pour réinitialiser votre mot de passe PROXIWORK.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/login.css')
    @endunless
@endpush

@section('auth_content')
    <div class="login-page">
        <section class="login-card" aria-labelledby="password-reset-request-title">
            <div class="login-card__header">
                <span class="login-eyebrow"><i class="fa-solid fa-key" aria-hidden="true"></i> RÉCUPÉRATION DU COMPTE</span>
                <h1 id="password-reset-request-title">Mot de passe oublié ?</h1>
                <p>Indiquez l’adresse e-mail de votre compte. Si elle correspond à un compte, vous recevrez un lien sécurisé.</p>
            </div>

            @if (session('status'))
                <div class="login-alert" role="status" tabindex="-1">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <div><strong>Demande traitée</strong><p>{{ session('status') }}</p></div>
                </div>
            @endif

            @if ($errors->any())
                <div class="login-alert" role="alert" tabindex="-1">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div><strong>Vérifiez votre adresse</strong><p>{{ $errors->first() }}</p></div>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="login-form">
                @csrf
                <div class="login-field">
                    <label for="email">Adresse e-mail</label>
                    <div class="login-input">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus>
                    </div>
                    @error('email') <p class="login-field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="login-submit">Envoyer le lien de réinitialisation</button>
            </form>

            <p class="login-register"><a href="{{ route('login') }}">Retour à la connexion</a></p>
        </section>
    </div>
@endsection
