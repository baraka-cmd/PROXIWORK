@extends('layouts.auth')

@section('title', 'Créer un compte client — PROXIWORK')
@section('meta_description', 'Créez votre compte client PROXIWORK et trouvez le professionnel adapté à votre besoin.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/register.css')
    @endunless
@endpush

@section('auth_content')
    <div class="register-page register-form-page">
        <section class="register-card" aria-labelledby="register-title">
            <a class="register-back-link" href="{{ route('register') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Choisir un autre parcours</a>
            <div class="register-card__header">
                <span class="register-eyebrow"><i class="fa-solid fa-compass" aria-hidden="true"></i> COMPTE CLIENT</span>
                <h1 id="register-title">Créez votre compte</h1>
                <p>Quelques informations suffisent pour commencer votre recherche.</p>
            </div>

            @if ($errors->any())
                <div class="register-alert" role="alert" tabindex="-1"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div><strong>Vérifiez les informations saisies</strong><p>{{ $errors->first() }}</p></div></div>
            @endif

            <form method="POST" action="{{ route('register.client.store') }}" class="register-form" data-register-form enctype="multipart/form-data">
                @csrf
                <div class="register-form-grid">
                    <div class="register-field">
                        <label for="first_name">Prénom</label>
                        <div class="register-input"><i class="fa-regular fa-user" aria-hidden="true"></i><input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" autocomplete="given-name" placeholder="Votre prénom" minlength="2" maxlength="100" required autofocus></div>
                        @error('first_name')<p class="register-field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </div>
                    <div class="register-field">
                        <label for="last_name">Nom</label>
                        <div class="register-input"><i class="fa-regular fa-user" aria-hidden="true"></i><input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name" placeholder="Votre nom" minlength="2" maxlength="100" required></div>
                        @error('last_name')<p class="register-field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="register-field">
                    <label for="phone">Numéro de téléphone</label>
                    <div class="register-input"><i class="fa-solid fa-phone" aria-hidden="true"></i><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" placeholder="+243 000 000 000" maxlength="30" required></div>
                    <small class="register-hint">Il sera utilisé pour faciliter les échanges liés à vos demandes.</small>
                    @error('phone')<p class="register-field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                </div>

                <div class="register-field">
                    <label for="email">Adresse e-mail</label>
                    <div class="register-input"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" placeholder="vous@exemple.com" maxlength="255" required></div>
                    @error('email')<p class="register-field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                </div>

                <div class="register-field register-photo-field">
                    <label for="profile_photo">Photo de profil <span>Facultative</span></label>
                    <input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <small class="register-hint">Image JPG, PNG ou WebP, 4 Mo maximum. Vous pourrez l’ajouter plus tard.</small>
                    @error('profile_photo')<p class="register-field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                </div>

                <div class="register-field">
                    <label for="password">Mot de passe</label>
                    <div class="register-input"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="password" name="password" type="password" autocomplete="new-password" placeholder="Créez une phrase de passe" minlength="12" required data-register-password><button class="register-password-toggle" type="button" data-register-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><i class="fa-regular fa-eye" aria-hidden="true"></i></button></div>
                    <div class="register-strength" id="password-strength" aria-live="polite">
                        <div class="register-strength__top"><span>Sécurité du mot de passe</span><strong data-register-strength-label>À saisir</strong></div>
                        <div class="register-strength__bar" aria-hidden="true"><span data-register-strength-bar></span></div>
                        <ul class="register-checks" aria-label="Critères du mot de passe"><li data-register-check="length"><i class="fa-solid fa-circle" aria-hidden="true"></i> 12 caractères minimum. Une phrase de passe est acceptée.</li></ul>
                    </div>
                    @error('password')<p class="register-field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                </div>

                <div class="register-field">
                    <label for="password_confirmation">Confirmer le mot de passe</label>
                    <div class="register-input" data-confirmation-container><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Saisissez à nouveau votre mot de passe" required data-register-confirm-password><button class="register-password-toggle" type="button" data-register-confirm-toggle aria-label="Afficher la confirmation du mot de passe" aria-pressed="false"><i class="fa-regular fa-eye" aria-hidden="true"></i></button></div>
                    <p class="register-match" data-register-match aria-live="polite"></p>
                </div>

                <label class="register-consent"><input type="checkbox" name="terms" value="1" required><span>J’accepte les conditions d’utilisation et la politique de confidentialité de PROXIWORK.</span></label>
                @error('terms')<p class="register-field-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror

                <button class="register-submit" type="submit" data-register-submit><span>Créer mon compte client</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
            </form>

            <p class="register-login">Vous avez déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
        </section>

        <aside class="register-aside" aria-labelledby="register-client-aside-title">
            <div class="register-aside__icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></div>
            <span class="register-aside__eyebrow">VOTRE ESPACE CLIENT</span>
            <h2 id="register-client-aside-title">Un compte simple pour faire avancer vos projets.</h2>
            <p>Votre photo reste facultative. Vous pourrez compléter vos informations depuis votre profil.</p>
            <ul class="register-benefits"><li><span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span><div><strong>Recherchez</strong><small>Découvrez des professionnels et leurs prestations.</small></div></li><li><span><i class="fa-solid fa-comments" aria-hidden="true"></i></span><div><strong>Échangez</strong><small>Gardez vos conversations réunies dans votre espace.</small></div></li><li><span><i class="fa-solid fa-list-check" aria-hidden="true"></i></span><div><strong>Suivez vos projets</strong><small>Retrouvez vos demandes, devis et commandes.</small></div></li></ul>
        </aside>
    </div>
@endsection

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/auth/register.js')
    @endunless
@endpush
