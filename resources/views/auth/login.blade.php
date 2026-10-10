@extends('layouts.auth')

@section('title', 'Connexion — PROXIWORK')
@section('meta_description', 'Connectez-vous à votre compte PROXIWORK pour accéder à votre espace.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/login.css')
    @endunless
@endpush

@section('auth_content')
    <div class="login-page">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-card__header">
                <span class="login-eyebrow">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    ESPACE PERSONNEL
                </span>

                <h1 id="login-title">Ravi de vous revoir</h1>
                <p>Connectez-vous pour retrouver vos échanges, demandes et activités sur PROXIWORK.</p>
            </div>

            @if ($errors->any())
                <div class="login-alert" role="alert" tabindex="-1">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Connexion impossible</strong>
                        <p>{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="login-form" data-login-form>
                @csrf

                <div class="login-field">
                    <label for="email">Adresse e-mail</label>
                    <div class="login-input">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            inputmode="email"
                            placeholder="vous@exemple.com"
                            aria-describedby="email-hint @error('email') email-error @enderror"
                            required
                            autofocus
                        >
                    </div>
                    <small id="email-hint" class="login-hint">Utilisez l’adresse associée à votre compte.</small>
                    @error('email')
                        <p id="email-error" class="login-field-error">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="login-field">
                    <div class="login-label-row">
                        <label for="password">Mot de passe</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}">Mot de passe oublié ?</a>
                        @endif
                    </div>

                    <div class="login-input">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Votre mot de passe"
                            aria-describedby="@error('password') password-error @enderror"
                            required
                        >
                        <button
                            class="login-password-toggle"
                            type="button"
                            data-login-password-toggle
                            aria-label="Afficher le mot de passe"
                            aria-pressed="false"
                        >
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    @error('password')
                        <p id="password-error" class="login-field-error">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Rester connecté</span>
                </label>

                <button class="login-submit" type="submit" data-login-submit>
                    <span>Se connecter</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="login-divider">
                <span>ou continuer avec</span>
            </div>

            @if (Route::has('auth.google.redirect'))
                <a class="google-login" href="{{ route('auth.google.redirect') }}">
                    <span class="google-login__icon" aria-hidden="true">G</span>
                    <span>Continuer avec Google</span>
                </a>
            @else
                <button class="google-login google-login--prepared" type="button" data-google-login aria-describedby="google-login-note">
                    <span class="google-login__icon" aria-hidden="true">G</span>
                    <span>Continuer avec Google</span>
                </button>
                <p id="google-login-note" class="google-login-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    La connexion Google sera activée avec la configuration OAuth de PROXIWORK.
                </p>
            @endif

            <p class="login-register">
                Vous n’avez pas encore de compte ?
                @if (Route::has('register'))
                    <a href="{{ route('register') }}">Créer un compte</a>
                @else
                    <span>Inscription prochainement</span>
                @endif
            </p>
        </section>

        <aside class="login-aside" aria-labelledby="login-aside-title">
            <div class="login-aside__glow" aria-hidden="true"></div>

            <div class="login-aside__icon" aria-hidden="true">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <span class="login-aside__eyebrow">VOTRE ESPACE PROXIWORK</span>
            <h2 id="login-aside-title">Tout votre réseau professionnel, au même endroit.</h2>
            <p>Retrouvez vos demandes, conversations et collaborations sans perdre le fil.</p>

            <ul class="login-benefits">
                <li>
                    <span><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
                    <div>
                        <strong>Échanges centralisés</strong>
                        <small>Suivez vos conversations avec les professionnels.</small>
                    </div>
                </li>
                <li>
                    <span><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
                    <div>
                        <strong>Activités organisées</strong>
                        <small>Gardez une vue claire sur vos demandes et services.</small>
                    </div>
                </li>
                <li>
                    <span><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                    <div>
                        <strong>Accès sécurisé</strong>
                        <small>Votre compte est protégé par l’authentification Laravel.</small>
                    </div>
                </li>
            </ul>

            <div class="login-trust">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>Connexion sécurisée et adaptée aux appareils mobiles.</span>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/auth/login.js')
    @endunless
@endpush
