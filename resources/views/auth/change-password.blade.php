@extends('layouts.auth')

@section('title', 'Modifier mon mot de passe — PROXIWORK')
@section('meta_description', 'Changez votre mot de passe PROXIWORK après vérification de votre mot de passe actuel.')

@section('auth_content')
    <section class="login-card" aria-labelledby="change-password-title">
        <div class="login-card__header">
            <span class="login-eyebrow"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> SÉCURITÉ DU COMPTE</span>
            <h1 id="change-password-title">Modifier mon mot de passe</h1>
            <p>Nous vérifions votre mot de passe actuel. Après le changement, toutes les sessions et tous les jetons d’accès seront révoqués et vous devrez vous reconnecter.</p>
        </div>

        @if ($errors->any())
            <div class="login-alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div><strong>Modification impossible</strong><p>{{ $errors->first() }}</p></div></div>
        @endif

        <form method="POST" action="{{ route('account.password.update') }}" class="login-form">
            @csrf
            <div class="login-field">
                <label for="current_password">Mot de passe actuel</label>
                <div class="login-input"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                @error('current_password')<p class="login-field-error">{{ $message }}</p>@enderror
            </div>
            <div class="login-field">
                <label for="password">Nouveau mot de passe</label>
                <div class="login-input"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
                <small class="login-hint">Au moins 8 caractères, avec majuscule, minuscule, chiffre et symbole.</small>
                @error('password')<p class="login-field-error">{{ $message }}</p>@enderror
            </div>
            <div class="login-field">
                <label for="password_confirmation">Confirmer le nouveau mot de passe</label>
                <div class="login-input"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>
            </div>
            <button type="submit" class="login-submit">Modifier le mot de passe</button>
        </form>

        <p class="login-register">
            <a href="{{ route('account.email.edit') }}">Modifier mon adresse e-mail</a>
            <span aria-hidden="true"> · </span>
            <a href="{{ route('dashboard') }}">Retour à mon espace</a>
        </p>
    </section>
@endsection
