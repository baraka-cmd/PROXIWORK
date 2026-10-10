@extends('layouts.auth')

@section('title', 'Modifier mon adresse e-mail — PROXIWORK')
@section('meta_description', 'Demandez le changement sécurisé de votre adresse e-mail PROXIWORK.')

@section('auth_content')
    <section class="login-card" aria-labelledby="change-email-title">
        <div class="login-card__header">
            <span class="login-eyebrow"><i class="fa-solid fa-envelope" aria-hidden="true"></i> SÉCURITÉ DU COMPTE</span>
            <h1 id="change-email-title">Modifier mon adresse e-mail</h1>
            <p>Votre adresse actuelle reste active jusqu’à la confirmation de la nouvelle adresse. Nous vous demanderons votre mot de passe actuel pour protéger cette opération.</p>
        </div>

        @if (session('status'))
            <div class="login-alert" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong>Demande traitée</strong><p>{{ session('status') }}</p></div></div>
        @endif

        @if ($errors->any())
            <div class="login-alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div><strong>Modification impossible</strong><p>{{ $errors->first() }}</p></div></div>
        @endif

        <p>Adresse actuelle : <strong>{{ $user->email }}</strong></p>
        @if ($user->pending_email)
            <p>Confirmation en attente pour : <strong>{{ $user->pending_email }}</strong></p>
        @endif

        <form method="POST" action="{{ route('account.email.update') }}" class="login-form">
            @csrf
            <div class="login-field">
                <label for="email">Nouvelle adresse e-mail</label>
                <div class="login-input"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input id="email" name="email" type="email" value="{{ old('email', $user->pending_email) }}" autocomplete="email" maxlength="255" required></div>
                @error('email')<p class="login-field-error">{{ $message }}</p>@enderror
            </div>
            <div class="login-field">
                <label for="current_password">Mot de passe actuel</label>
                <div class="login-input"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                @error('current_password')<p class="login-field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="login-submit">Envoyer le lien de confirmation</button>
        </form>

        <p class="login-register">
            <a href="{{ route('account.password.edit') }}">Modifier mon mot de passe</a>
            <span aria-hidden="true"> · </span>
            <a href="{{ route('dashboard') }}">Retour à mon espace</a>
        </p>
    </section>
@endsection
