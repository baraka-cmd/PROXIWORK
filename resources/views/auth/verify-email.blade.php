@extends('layouts.auth')

@section('title', 'Vérifier votre adresse e-mail — PROXIWORK')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/register.css')
    @endunless
@endpush

@section('auth_content')
    <div class="register-page">
        <section class="register-card" aria-labelledby="verify-email-title">
            <div class="register-card__header">
                <span class="register-eyebrow">
                    <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
                    SÉCURITÉ DU COMPTE
                </span>

                <h1 id="verify-email-title">Vérifiez votre adresse e-mail</h1>
                <p>
                    Un lien de confirmation a été envoyé à l’adresse associée à votre compte.
                    Utilisez-le pour sécuriser votre accès PROXIWORK.
                </p>
            </div>

            @if (session('status'))
                <div class="register-alert" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}" class="register-form">
                @csrf
                <button class="register-submit" type="submit">
                    <span>Renvoyer le lien</span>
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="register-login">
                @csrf
                <button type="submit" class="button button--ghost">Se déconnecter</button>
            </form>
        </section>
    </div>
@endsection