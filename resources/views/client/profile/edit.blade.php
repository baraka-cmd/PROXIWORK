@extends('layouts.client')

@section('title', 'Modifier mon profil')

@section('content')
@php
    $profileDisplayName = trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name;
    $profileInitials = collect(preg_split('/\\s+/', trim($profileDisplayName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp
@push('head')
    @vite(['resources/css/pages/client/profile.css', 'resources/js/pages/client/profile.js'])
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

    @if ($errors->any())
        <div id="profile-error-summary" class="client-panel profile-feedback profile-feedback--error profile-error-summary" role="alert" aria-live="assertive" tabindex="-1" data-error-summary>
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Vérifiez les informations signalées.</strong>
                <p>Choisissez un message pour accéder au champ à corriger. Chaque section est enregistrée séparément.</p>
                <ul>
                    @foreach ($errors->getMessages() as $field => $messages)
                        @foreach ($messages as $message)
                            @if ($field === 'current_password')
                                <li>{{ $message }} — <a href="#email_current_password">Adresse e-mail</a> ou <a href="#current_password">mot de passe</a></li>
                            @else
                                <li><a href="#{{ $field === 'email' ? 'new_email' : $field }}">{{ $message }}</a></li>
                            @endif
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <nav class="profile-settings-nav client-panel" aria-label="Navigation des paramètres du profil">
        <p class="profile-section-kicker">ACCÈS RAPIDE</p>
        <div class="profile-settings-nav__links">
            <a href="#profile-personal"><i class="fa-solid fa-user" aria-hidden="true"></i> Informations personnelles</a>
            <a href="#profile-photo"><i class="fa-solid fa-camera" aria-hidden="true"></i> Photo</a>
            <a href="#profile-email"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Adresse e-mail</a>
            <a href="#profile-password"><i class="fa-solid fa-lock" aria-hidden="true"></i> Mot de passe</a>
        </div>
    </nav>

    <section id="profile-photo" class="client-panel profile-section" aria-labelledby="profile-photo-title">
        <div class="profile-section-heading">
            <div>
                <p class="profile-section-kicker">PERSONNALISATION</p>
                <h2 id="profile-photo-title">Photo de profil</h2>
                <p class="profile-muted-copy">Facultative. JPG, PNG ou WebP, maximum 2 Mo et 2 000 × 2 000 pixels.</p>
            </div>
        </div>

        <div class="profile-photo-editor">
            <div class="profile-photo-current">
                <div class="profile-avatar profile-avatar--large">
                    @if ($user->profile?->avatar_path)
                        <img src="{{ route('client.profile.photo') }}" alt="Photo de profil actuelle de {{ $profileDisplayName }}">
                    @else
                        <span aria-hidden="true">{{ $profileInitials }}</span>
                    @endif
                </div>
                <strong>Photo actuelle</strong>
                <small class="profile-muted-copy">Visible dans votre espace client.</small>
            </div>
            <div class="profile-photo-actions">
                <form method="POST" action="{{ route('client.profile.photo.store') }}" enctype="multipart/form-data" class="profile-form profile-photo-form">
                    @csrf
                    <label for="avatar">Choisir une nouvelle photo
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required aria-invalid="@error('avatar') true @else false @enderror" aria-describedby="avatar-help avatar-client-error @error('avatar') avatar-server-error @enderror">
                    </label>
                    <small id="avatar-help" class="profile-muted-copy">JPG, PNG ou WebP · 2 Mo maximum · 2 000 × 2 000 pixels maximum. Le fichier est contrôlé à nouveau par le serveur et reste stocké dans un espace privé.</small>
                    <p id="avatar-client-error" class="profile-field-error" role="status" aria-live="polite" hidden></p>
                    <div class="profile-photo-new-preview" data-avatar-preview hidden>
                        <img class="profile-photo-preview" alt="Aperçu de la nouvelle photo de profil">
                        <div><strong data-avatar-filename></strong><small class="profile-muted-copy">Aperçu local — la photo ne sera enregistrée qu’après validation.</small></div>
                    </div>
                    @error('avatar')<p id="avatar-server-error" class="profile-field-error">{{ $message }}</p>@enderror
                    <button class="btn btn-primary" type="submit" data-submit-label="Enregistrement de la photo…"><i class="fa-solid fa-upload" aria-hidden="true"></i> Enregistrer la photo</button>
                </form>
                @if ($user->profile?->avatar_path)
                    <form method="POST" action="{{ route('client.profile.photo.destroy') }}" data-confirm data-confirm-title="Supprimer la photo ?" data-confirm-message="La photo de profil sera supprimée. Votre nom et les autres informations du compte resteront inchangés." data-confirm-label="Supprimer la photo">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger-outline" type="submit"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Supprimer la photo</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section id="profile-personal" class="client-panel profile-section" aria-labelledby="profile-personal-title">
        <div class="profile-section-heading">
            <div>
                <p class="profile-section-kicker">IDENTITÉ DU COMPTE</p>
                <h2 id="profile-personal-title">Informations personnelles</h2>
                <p class="profile-muted-copy">Le rôle, l’état du compte et les informations de vérification ne sont pas modifiables ici.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('client.profile.update') }}" class="profile-form" data-submit-label="Enregistrement des informations…">
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
                <button class="btn btn-primary" type="submit" data-submit-label="Enregistrement des informations…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Enregistrer les informations</button>
            </div>
        </form>
    </section>

    <section id="profile-email" class="client-panel profile-section" aria-labelledby="profile-email-title">
        <div class="profile-section-heading">
            <div>
                <p class="profile-section-kicker">COORDONNÉES DU COMPTE</p>
                <h2 id="profile-email-title">Modifier l’adresse e-mail</h2>
                <p class="profile-muted-copy">Votre adresse actuelle reste active jusqu’à ce que vous confirmiez le lien envoyé à la nouvelle adresse.</p>
            </div>
            <span class="profile-section-icon" aria-hidden="true"><i class="fa-solid fa-envelope"></i></span>
        </div>

        @if ($user->pending_email && $user->pending_email_expires_at?->isFuture())
            <div class="profile-pending-email" role="status">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                <div>
                    <strong>Confirmation en attente</strong>
                    <p>Nouvelle adresse : <strong>{{ $user->pending_email }}</strong>. Le lien expire le {{ $user->pending_email_expires_at->format('d/m/Y à H:i') }}. Votre adresse de connexion actuelle reste active jusqu’à la confirmation.</p>
                </div>
            </div>
        @elseif ($user->pending_email && $user->pending_email_expires_at?->isPast())
            <div class="profile-pending-email profile-pending-email--expired" role="status">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <div>
                    <strong>Le lien de confirmation a expiré</strong>
                    <p>La demande pour {{ $user->pending_email }} n’a pas modifié votre adresse de connexion. Envoyez une nouvelle demande ci-dessous si vous souhaitez toujours la changer.</p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('client.profile.email-change.store') }}" class="profile-form" data-submit-label="Envoi de la demande…">
            @csrf
            <label for="new_email">Nouvelle adresse e-mail
                <input id="new_email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" aria-invalid="@error('email') true @else false @enderror" aria-describedby="@error('email') email-change-error @enderror">
                @error('email')<small id="email-change-error" class="profile-field-error">{{ $message }}</small>@enderror
            </label>

            <div class="profile-password-field">
                <label for="email_current_password">Mot de passe actuel</label>
                <div class="profile-password-control">
                    <input id="email_current_password" type="password" name="current_password" required autocomplete="current-password" aria-invalid="@error('current_password') true @else false @enderror" aria-describedby="@error('current_password') email-password-error @enderror">
                    <button type="button" class="profile-password-toggle" data-password-toggle aria-controls="email_current_password" aria-pressed="false" aria-label="Afficher le mot de passe actuel pour le changement d’adresse e-mail">Afficher</button>
                </div>
                @error('current_password')<small id="email-password-error" class="profile-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="profile-form-actions">
                <button class="btn btn-primary" type="submit" data-submit-label="Envoi de la demande…"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Envoyer le lien de confirmation</button>
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

        <form method="POST" action="{{ route('client.profile.password.update') }}" class="profile-form" data-submit-label="Mise à jour du mot de passe…">
            @csrf
            @method('PATCH')

            <div class="profile-password-field">
                <label for="current_password">Mot de passe actuel</label>
                <div class="profile-password-control">
                    <input id="current_password" type="password" name="current_password" required autocomplete="current-password" aria-invalid="@error('current_password') true @else false @enderror" aria-describedby="@error('current_password') current-password-error @enderror">
                    <button type="button" class="profile-password-toggle" data-password-toggle aria-controls="current_password" aria-pressed="false" aria-label="Afficher le mot de passe actuel">Afficher</button>
                </div>
                @error('current_password')<small id="current-password-error" class="profile-field-error">{{ $message }}</small>@enderror
            </div>

            <div class="profile-form-grid">
                <div class="profile-password-field">
                    <label for="password">Nouveau mot de passe</label>
                    <div class="profile-password-control">
                        <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-invalid="@error('password') true @else false @enderror" aria-describedby="password-help @error('password') password-error @enderror">
                        <button type="button" class="profile-password-toggle" data-password-toggle aria-controls="password" aria-pressed="false" aria-label="Afficher le nouveau mot de passe">Afficher</button>
                    </div>
                    <small id="password-help" class="profile-muted-copy">Au moins 8 caractères, avec majuscule, minuscule, chiffre et symbole.</small>
                    @error('password')<small id="password-error" class="profile-field-error">{{ $message }}</small>@enderror
                </div>

                <div class="profile-password-field">
                    <label for="password_confirmation">Confirmer le nouveau mot de passe</label>
                    <div class="profile-password-control">
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" data-password-match aria-invalid="@error('password_confirmation') true @else false @enderror" aria-describedby="password-match-error @error('password_confirmation') password-confirmation-error @enderror">
                        <button type="button" class="profile-password-toggle" data-password-toggle aria-controls="password_confirmation" aria-pressed="false" aria-label="Afficher la confirmation du nouveau mot de passe">Afficher</button>
                    </div>
                    <small id="password-match-error" class="profile-field-error" role="status" aria-live="polite" hidden></small>
                    @error('password_confirmation')<small id="password-confirmation-error" class="profile-field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="profile-form-actions">
                <button class="btn btn-primary" type="submit" data-submit-label="Mise à jour du mot de passe…"><i class="fa-solid fa-key" aria-hidden="true"></i> Mettre à jour le mot de passe</button>
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
