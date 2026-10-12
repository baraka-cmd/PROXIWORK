@extends('layouts.client')

@section('title', 'Modifier mon profil')

@section('content')
@push('head')
    @vite('resources/css/pages/client/profile.css')
@endpush

<div class="client-profile-page">
    <header class="client-module__header profile-page-header">
        <div>
            <p class="client-module__eyebrow">ESPACE PERSONNEL</p>
            <h1>Paramètres du profil</h1>
            <p>Chaque section est enregistrée séparément. Les changements ne sont confirmés qu’après leur sauvegarde.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('client.profile') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Retour au profil
        </a>
    </header>

    @if (session('success'))
        <div class="client-panel profile-feedback profile-feedback--success" role="status" aria-live="polite">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="client-panel profile-feedback profile-feedback--error" role="alert" aria-live="assertive">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Vérifiez les informations indiquées.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="client-panel profile-section" aria-labelledby="profile-photo-title">
        <div class="profile-section-heading">
            <div>
                <p class="profile-section-kicker">PERSONNALISATION</p>
                <h2 id="profile-photo-title">Photo de profil</h2>
                <p class="profile-muted-copy">Facultative. JPG, PNG ou WebP, maximum 2 Mo et 2 000 × 2 000 pixels.</p>
            </div>
        </div>

        <div class="profile-photo-editor">
            <div class="profile-avatar profile-avatar--large">
                @if ($user->profile?->avatar_path)
                    <img src="{{ route('client.profile.photo') }}" alt="Photo de profil actuelle">
                @else
                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr(trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name, 0, 1)) }}</span>
                @endif
            </div>
            <div class="profile-photo-actions">
                <form method="POST" action="{{ route('client.profile.photo.store') }}" enctype="multipart/form-data" class="profile-form profile-photo-form">
                    @csrf
                    <label for="avatar">Choisir une nouvelle photo
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required aria-describedby="avatar-help">
                    </label>
                    <small id="avatar-help" class="profile-muted-copy">L’image est contrôlée côté serveur et stockée dans un espace non public.</small>
                    @error('avatar')<p class="profile-field-error">{{ $message }}</p>@enderror
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-upload" aria-hidden="true"></i> Enregistrer la photo</button>
                </form>
                @if ($user->profile?->avatar_path)
                    <form method="POST" action="{{ route('client.profile.photo.destroy') }}" onsubmit="return confirm('Supprimer votre photo de profil ? Les autres informations resteront inchangées.');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger-outline" type="submit"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Supprimer la photo</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section class="client-panel profile-section" aria-labelledby="profile-personal-title">
        <div class="profile-section-heading">
            <div>
                <p class="profile-section-kicker">IDENTITÉ DU COMPTE</p>
                <h2 id="profile-personal-title">Informations personnelles</h2>
                <p class="profile-muted-copy">Le rôle, l’état du compte et les informations de vérification ne sont pas modifiables ici.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('client.profile.update') }}" class="profile-form">
            @csrf
            @method('PATCH')

            <div class="profile-form-grid">
                <label for="first_name">Prénom <span aria-hidden="true">*</span>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $user->profile?->first_name) }}" required maxlength="100" autocomplete="given-name" aria-invalid="@error('first_name') true @else false @enderror" aria-describedby="@error('first_name') first-name-error @enderror">
                    @error('first_name')<small id="first-name-error" class="profile-field-error">{{ $message }}</small>@enderror
                </label>

                <label for="last_name">Nom <span aria-hidden="true">*</span>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $user->profile?->last_name) }}" required maxlength="100" autocomplete="family-name" aria-invalid="@error('last_name') true @else false @enderror" aria-describedby="@error('last_name') last-name-error @enderror">
                    @error('last_name')<small id="last-name-error" class="profile-field-error">{{ $message }}</small>@enderror
                </label>

                <label for="email">Adresse e-mail
                    <input id="email" type="email" value="{{ $user->email }}" readonly aria-describedby="email-help">
                    <small id="email-help" class="profile-muted-copy">L’adresse de connexion reste inchangée ici afin de préserver la vérification du compte. Son changement nécessite un parcours de confirmation dédié.</small>
                </label>

                <label for="phone">Téléphone
                    <input id="phone" type="tel" name="phone" value="{{ old('phone', $user->profile?->phone) }}" maxlength="30" autocomplete="tel" placeholder="Ex. : +243…" aria-invalid="@error('phone') true @else false @enderror" aria-describedby="@error('phone') phone-error @enderror">
                    @error('phone')<small id="phone-error" class="profile-field-error">{{ $message }}</small>@enderror
                    <small class="profile-muted-copy">L’enregistrement d’un numéro ne signifie pas qu’il a été vérifié.</small>
                </label>
            </div>

            <label for="bio">À propos <span class="profile-optional">(facultatif)</span>
                <textarea id="bio" name="bio" rows="4" maxlength="2000" autocomplete="off" aria-invalid="@error('bio') true @else false @enderror" aria-describedby="bio-help @error('bio') bio-error @enderror" placeholder="Présentez-vous en quelques mots.">{{ old('bio', $user->profile?->bio) }}</textarea>
                <small id="bio-help" class="profile-muted-copy">Maximum 2 000 caractères. N’incluez pas de mot de passe ni d’informations financières sensibles.</small>
                @error('bio')<small id="bio-error" class="profile-field-error">{{ $message }}</small>@enderror
            </label>

            <div class="profile-form-actions">
                <a class="btn btn-secondary" href="{{ route('client.profile') }}">Annuler</a>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Enregistrer les informations</button>
            </div>
        </form>
    </section>

    <section id="profile-password" class="client-panel profile-section" aria-labelledby="profile-password-title">
        <div class="profile-section-heading">
            <div>
                <p class="profile-section-kicker">PROTECTION DU COMPTE</p>
                <h2 id="profile-password-title">Modifier le mot de passe</h2>
                <p class="profile-muted-copy">Votre mot de passe actuel est requis. Après le changement, les autres sessions et les jetons API seront révoqués.</p>
            </div>
            <span class="profile-section-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
        </div>

        <form method="POST" action="{{ route('client.profile.password.update') }}" class="profile-form">
            @csrf
            @method('PATCH')

            <label for="current_password">Mot de passe actuel
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" aria-invalid="@error('current_password') true @else false @enderror" aria-describedby="@error('current_password') current-password-error @enderror">
                @error('current_password')<small id="current-password-error" class="profile-field-error">{{ $message }}</small>@enderror
            </label>

            <div class="profile-form-grid">
                <label for="password">Nouveau mot de passe
                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-invalid="@error('password') true @else false @enderror" aria-describedby="password-help @error('password') password-error @enderror">
                    <small id="password-help" class="profile-muted-copy">Au moins 8 caractères, avec majuscule, minuscule, chiffre et symbole.</small>
                    @error('password')<small id="password-error" class="profile-field-error">{{ $message }}</small>@enderror
                </label>

                <label for="password_confirmation">Confirmer le nouveau mot de passe
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </label>
            </div>

            <div class="profile-form-actions">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-key" aria-hidden="true"></i> Mettre à jour le mot de passe</button>
            </div>
        </form>
    </section>

    <section class="client-panel profile-privacy-note" aria-labelledby="profile-privacy-title">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <div>
            <h2 id="profile-privacy-title">Vos données et votre compte</h2>
            <p>Les adresses d’intervention sont gérées séparément. Les commandes et paiements historiques ne sont pas modifiés lorsque vous mettez à jour ce profil. L’export et la suppression du compte ne sont pas proposés ici tant qu’un processus complet de conservation et de traitement des opérations en cours n’est pas défini.</p>
            <a class="profile-text-link" href="{{ route('client.addresses.index') }}">Gérer mes adresses <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </section>
</div>
@endsection
