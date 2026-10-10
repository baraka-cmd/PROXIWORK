@extends('layouts.auth')

@section('title', 'Vérification de l’adresse e-mail — PROXIWORK')
@section('meta_description', 'Confirmez votre adresse e-mail pour sécuriser votre compte PROXIWORK.')

@section('auth_content')
    <section class="login-card" aria-labelledby="verify-email-title">
        <div class="login-card__header">
            <span class="login-eyebrow"><i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i> SÉCURITÉ DU COMPTE</span>
            <h1 id="verify-email-title">Vérifiez votre adresse e-mail</h1>
            <p>Nous devons confirmer que vous contrôlez l’adresse associée à votre compte. Cette étape est distincte de la vérification de votre identité ou de votre dossier professionnel.</p>
        </div>

        @if (session('status'))
            <div class="login-alert" role="status" tabindex="-1">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <div><strong>Information</strong><p>{{ session('status') }}</p></div>
            </div>
        @endif

        @if ($errors->any())
            <div class="login-alert" role="alert" tabindex="-1">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div><strong>Vérification impossible</strong><p>{{ $errors->first() }}</p></div>
            </div>
        @endif

        <p>Connecté avec : <strong>{{ auth()->user()->email }}</strong></p>

        <form method="POST" action="{{ route('verification.send') }}" class="login-form">
            @csrf
            <button type="submit" class="login-submit">Renvoyer le lien de vérification</button>
        </form>

        <p class="login-register">
            <a href="{{ route('dashboard') }}">Continuer vers mon espace</a>
        </p>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="button button--secondary">Se déconnecter</button>
        </form>
    </section>
@endsection
