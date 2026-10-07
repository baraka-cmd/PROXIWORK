@extends('layouts.auth')

@section('title', 'Créer un compte — PROXIWORK')
@section('meta_description', 'Créez votre compte PROXIWORK pour rejoindre votre réseau professionnel.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/register.css')
    @endunless
@endpush

@section('auth_content')
    <div class="register-page">
        <section class="register-card" aria-labelledby="register-title">
            <div class="register-card__header">
                <span class="register-eyebrow">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    NOUVEAU COMPTE
                </span>

                <h1 id="register-title">Créez votre compte</h1>
                <p>Rejoignez PROXIWORK et commencez à développer votre réseau professionnel.</p>
            </div>

            @if ($errors->any())
                <div class="register-alert" role="alert" tabindex="-1">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Vérifiez les informations saisies</strong>
                        <p>{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="register-form" data-register-form novalidate>
                @csrf

                <div class="register-field">
                    <label for="name">Nom complet</label>
                    <div class="register-input">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            autocomplete="name"
                            placeholder="Votre nom complet"
                            minlength="2"
                            maxlength="100"
                            aria-describedby="name-hint @error('name') name-error @enderror"
                            required
                            autofocus
                        >
                    </div>
                    <small id="name-hint" class="register-hint">Utilisez votre vrai nom pour faciliter les échanges professionnels.</small>
                    @error('name')
                        <p id="name-error" class="register-field-error">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="register-field">
                    <label for="email">Adresse e-mail</label>
                    <div class="register-input">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            inputmode="email"
                            placeholder="vous@exemple.com"
                            maxlength="255"
                            aria-describedby="email-hint @error('email') email-error @enderror"
                            required
                        >
                    </div>
                    <small id="email-hint" class="register-hint">Cette adresse servira à vous connecter à PROXIWORK.</small>
                    @error('email')
                        <p id="email-error" class="register-field-error">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="register-field">
                    <label for="password">Mot de passe</label>
                    <div class="register-input">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Créez un mot de passe sécurisé"
                            minlength="8"
                            aria-describedby="password-strength @error('password') password-error @enderror"
                            required
                            data-register-password
                        >
                        <button
                            class="register-password-toggle"
                            type="button"
                            data-register-password-toggle
                            aria-label="Afficher le mot de passe"
                            aria-pressed="false"
                        >
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="register-strength" id="password-strength" aria-live="polite">
                        <div class="register-strength__top">
                            <span>Sécurité du mot de passe</span>
                            <strong data-register-strength-label>À saisir</strong>
                        </div>
                        <div class="register-strength__bar" aria-hidden="true">
                            <span data-register-strength-bar></span>
                        </div>
                        <ul class="register-checks" aria-label="Critères du mot de passe">
                            <li data-register-check="length"><i class="fa-solid fa-circle" aria-hidden="true"></i> 8 caractères minimum</li>
                            <li data-register-check="lower"><i class="fa-solid fa-circle" aria-hidden="true"></i> Une minuscule</li>
                            <li data-register-check="upper"><i class="fa-solid fa-circle" aria-hidden="true"></i> Une majuscule</li>
                            <li data-register-check="number"><i class="fa-solid fa-circle" aria-hidden="true"></i> Un chiffre</li>
                        </ul>
                    </div>

                    @error('password')
                        <p id="password-error" class="register-field-error">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="register-field">
                    <label for="password_confirmation">Confirmer le mot de passe</label>
                    <div class="register-input" data-confirmation-container>
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Saisissez à nouveau votre mot de passe"
                            aria-describedby="password-confirmation-status"
                            required
                            data-register-confirm-password
                        >
                        <button
                            class="register-password-toggle"
                            type="button"
                            data-register-confirm-toggle
                            aria-label="Afficher la confirmation du mot de passe"
                            aria-pressed="false"
                        >
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p id="password-confirmation-status" class="register-match" data-register-match aria-live="polite"></p>
                </div>

                <label class="register-consent">
                    <input type="checkbox" name="terms" value="1" required data-register-terms>
                    <span>J’accepte les conditions d’utilisation de PROXIWORK et je confirme que les informations fournies sont exactes.</span>
                </label>

                <button class="register-submit" type="submit" data-register-submit>
                    <span>Créer mon compte</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="register-divider">
                <span>ou continuer avec</span>
            </div>

            @if (Route::has('auth.google.redirect'))
                <a class="google-register" href="{{ route('auth.google.redirect') }}">
                    <span class="google-register__icon" aria-hidden="true">G</span>
                    <span>Continuer avec Google</span>
                </a>
            @else
                <button class="google-register google-register--prepared" type="button" data-google-register aria-describedby="google-register-note">
                    <span class="google-register__icon" aria-hidden="true">G</span>
                    <span>Continuer avec Google</span>
                </button>
                <p id="google-register-note" class="google-register-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    La connexion Google sera activée avec la configuration OAuth de PROXIWORK.
                </p>
            @endif

            <p class="register-login">
                Vous avez déjà un compte ?
                <a href="{{ route('login') }}">Se connecter</a>
            </p>
        </section>

        <aside class="register-aside" aria-labelledby="register-aside-title">
            <div class="register-aside__glow" aria-hidden="true"></div>

            <div class="register-aside__icon" aria-hidden="true">
                <i class="fa-solid fa-handshake"></i>
            </div>

            <span class="register-aside__eyebrow">BIENVENUE SUR PROXIWORK</span>
            <h2 id="register-aside-title">Un compte pour construire de vraies opportunités.</h2>
            <p>Créez votre identité PROXIWORK une seule fois, puis développez vos échanges et collaborations.</p>

            <ul class="register-benefits">
                <li>
                    <span><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                    <div>
                        <strong>Profil professionnel</strong>
                        <small>Construisez progressivement une présence claire et crédible.</small>
                    </div>
                </li>
                <li>
                    <span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                    <div>
                        <strong>Opportunités ciblées</strong>
                        <small>Retrouvez des services et des professionnels adaptés à vos besoins.</small>
                    </div>
                </li>
                <li>
                    <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <div>
                        <strong>Compte protégé</strong>
                        <small>Vos identifiants sont traités par l’authentification Laravel.</small>
                    </div>
                </li>
            </ul>

            <div class="register-trust">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>Inscription sécurisée, responsive et pensée pour un usage professionnel.</span>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/auth/register.js')
    @endunless
@endpush
