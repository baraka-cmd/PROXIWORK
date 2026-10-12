@extends('layouts.client')

@section('title', 'Mon profil')

@section('content')
@push('head')
    @vite('resources/css/pages/client/profile.css')
@endpush

<div class="client-profile-page">
    <header class="client-module__header profile-page-header">
        <div>
            <p class="client-module__eyebrow">ESPACE PERSONNEL</p>
            <h1>Mon profil</h1>
            <p>Gérez les informations de votre compte et protégez votre accès à PROXIWORK.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('client.profile.edit') }}">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            Modifier mon profil
        </a>
    </header>

    @if (session('success'))
        <div class="client-panel client-alert profile-feedback profile-feedback--success" role="status" aria-live="polite">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            {{ session('success') }}
        </div>
    @endif

    <section class="profile-hero client-panel" aria-labelledby="profile-identity-title">
        <div class="profile-avatar">
            @if ($user->profile?->avatar_path)
                <img src="{{ route('client.profile.photo') }}" alt="Photo de profil de {{ $user->name }}">
            @else
                <span aria-hidden="true">{{ mb_strtoupper(mb_substr(trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name, 0, 1)) }}</span>
            @endif
        </div>
        <div class="profile-identity">
            <p class="profile-section-kicker">COMPTE CLIENT</p>
            <h2 id="profile-identity-title">{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name }}</h2>
            <p>{{ $user->email }}</p>
            <div class="profile-status-list">
                <span class="profile-status-pill">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    Client
                </span>
                @if ($user->hasVerifiedEmail())
                    <span class="profile-status-pill profile-status-pill--success">
                        <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
                        E-mail vérifié
                    </span>
                @else
                    <span class="profile-status-pill profile-status-pill--warning">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        E-mail non vérifié
                    </span>
                @endif
            </div>
        </div>
        <div class="profile-member-since">
            <span>Membre depuis</span>
            <strong>{{ optional($user->created_at)->format('d/m/Y') ?? '—' }}</strong>
        </div>
    </section>

    @if (! $user->hasVerifiedEmail())
        <section class="client-panel profile-verification-note" aria-label="Vérification de l’adresse e-mail">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <div>
                <h2>Confirmez votre adresse e-mail</h2>
                <p>La vérification confirme que vous avez accès à cette boîte e-mail. Elle ne constitue pas une vérification de votre identité civile.</p>
                @if (Route::has('verification.send'))
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button class="btn btn-secondary" type="submit">Renvoyer le lien de vérification</button>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <section class="client-panel profile-section" aria-labelledby="profile-details-title">
        <div class="profile-section-heading">
            <div>
                <p class="profile-section-kicker">VOS INFORMATIONS</p>
                <h2 id="profile-details-title">Informations personnelles</h2>
            </div>
            <a class="profile-text-link" href="{{ route('client.profile.edit') }}">Modifier <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>

        <dl class="profile-details-grid">
            <div>
                <dt>Prénom</dt>
                <dd>{{ $user->profile?->first_name ?: 'Non renseigné' }}</dd>
            </div>
            <div>
                <dt>Nom</dt>
                <dd>{{ $user->profile?->last_name ?: 'Non renseigné' }}</dd>
            </div>
            <div>
                <dt>Adresse e-mail</dt>
                <dd class="profile-value-with-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i>{{ $user->email }}</dd>
            </div>
            <div>
                <dt>Téléphone</dt>
                <dd>{{ $user->profile?->phone ?: 'Non renseigné' }}</dd>
            </div>
            <div class="profile-detail-wide">
                <dt>À propos</dt>
                <dd class="profile-bio-value">{{ $user->profile?->bio ?: 'Vous pouvez ajouter une courte présentation facultative.' }}</dd>
            </div>
        </dl>
    </section>

    <section class="profile-shortcuts" aria-label="Autres paramètres du compte">
        <a class="client-panel profile-shortcut" href="{{ route('client.addresses.index') }}">
            <span class="profile-shortcut-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
            <span><strong>Mes adresses</strong><small>Gérer les lieux d’intervention enregistrés</small></span>
            <i class="fa-solid fa-chevron-right profile-shortcut-arrow" aria-hidden="true"></i>
        </a>
        <a class="client-panel profile-shortcut" href="{{ route('client.profile.edit') }}#profile-password">
            <span class="profile-shortcut-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
            <span><strong>Sécurité du compte</strong><small>Modifier votre mot de passe et protéger vos connexions</small></span>
            <i class="fa-solid fa-chevron-right profile-shortcut-arrow" aria-hidden="true"></i>
        </a>
    </section>
</div>
@endsection
