@extends('layouts.admin')

@section('page_eyebrow', 'COMPTE UTILISATEUR')
@section('page_title', $user->name)
@section('page_description', $user->email)

@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.users.index') }}"><i class="fa-solid fa-arrow-left"></i> Retour</a>
@endsection

@section('content')
    @if(session('success'))<div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="admin-alert admin-alert--error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i>{{ $errors->first() }}</div>@endif

    <div class="admin-detail-grid">
        <section class="admin-card admin-profile-hero">
            <div class="admin-profile-hero__avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
            <div class="admin-profile-hero__body">
                <span class="admin-card__eyebrow">Identité</span>
                <h3>{{ $user->name }}</h3>
                <p>{{ $user->email }}</p>
                <div class="admin-chip-list">
                    @forelse($user->roles as $role)<span class="admin-chip admin-chip--accent">{{ $role->display_name }}</span>@empty<span class="admin-muted">Aucun rôle</span>@endforelse
                </div>
            </div>
            <span class="admin-status admin-status--{{ $user->account_status?->value }}">{{ $user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</span>
        </section>

        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Compte</span><h3>Informations</h3></div></div>
            <dl class="admin-definition-list">
                <div><dt>ID</dt><dd>#{{ $user->id }}</dd></div>
                <div><dt>E-mail</dt><dd>{{ $user->email }}</dd></div>
                <div><dt>E-mail vérifié</dt><dd>{{ $user->email_verified_at ? $user->email_verified_at->format('d/m/Y H:i') : 'Non vérifié' }}</dd></div>
                <div><dt>Inscription</dt><dd>{{ $user->created_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Dernière modification</dt><dd>{{ $user->updated_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Dernière connexion</dt><dd>{{ $details['last_login'] ? \Illuminate\Support\Carbon::parse($details['last_login'])->format('d/m/Y H:i') : 'Aucune connexion historisée' }}</dd></div>
                <div><dt>Téléphone</dt><dd>{{ $user->profile?->phone ?: 'Non renseigné' }} · vérification téléphonique non prise en charge</dd></div>
                <div><dt>Prénom et nom</dt><dd>{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: 'Non renseignés' }}</dd></div>
                <div><dt>Type de compte</dt><dd>{{ $user->roles->pluck('display_name')->join(', ') ?: 'Aucun rôle' }} · lecture seule</dd></div>
            </dl>
        </section>

        @if($user->hasRole('client'))
            <section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Profil client</span><h3>Activité commerciale</h3></div></div>
                <dl class="admin-definition-list"><div><dt>Demandes de services</dt><dd>{{ $details['client']['requests'] }}</dd></div><div><dt>Devis reçus</dt><dd>{{ $details['client']['quotations'] }}</dd></div><div><dt>Commandes</dt><dd>{{ $details['client']['orders'] }}</dd></div><div><dt>Terminées</dt><dd>{{ $details['client']['completed_orders'] }}</dd></div><div><dt>Annulées</dt><dd>{{ $details['client']['cancelled_orders'] }}</dd></div><div><dt>Litiges</dt><dd>{{ $details['client']['disputes'] }}</dd></div></dl>
            </section>
        @endif

        @if($details['professional'])
            <section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Profil professionnel</span><h3>Présentation et activité</h3></div></div>
                <dl class="admin-definition-list"><div><dt>Titre</dt><dd>{{ $user->professionalProfile->professional_title ?: 'Non renseigné' }}</dd></div><div><dt>Localisation</dt><dd>{{ collect([$user->professionalProfile->city, $user->professionalProfile->commune, $user->professionalProfile->province])->filter()->join(', ') ?: 'Non renseignée' }}</dd></div><div><dt>Vérification administrative</dt><dd>{{ $details['professional']['verification'] ?: 'Non disponible' }}</dd></div><div><dt>Profil complet</dt><dd>{{ $details['professional']['profile_complete'] ? 'Oui' : 'Non' }}</dd></div><div><dt>Services publiés / total</dt><dd>{{ $details['professional']['published_services'] }} / {{ $details['professional']['services'] }}</dd></div><div><dt>Demandes reçues</dt><dd>{{ $details['professional']['requests'] }}</dd></div><div><dt>Devis</dt><dd>{{ $details['professional']['quotations'] }}</dd></div><div><dt>Commandes</dt><dd>{{ $details['professional']['orders'] }}</dd></div><div><dt>Note / avis admissibles</dt><dd>{{ $details['professional']['rating'] ?? '0.00' }} / {{ $details['professional']['reviews'] }}</dd></div><div><dt>Disponibilité</dt><dd>{{ $details['professional']['availability'] ?: 'Non renseignée' }}</dd></div><div><dt>Compétences</dt><dd>{{ $user->professionalProfile->skills->pluck('name')->join(', ') ?: 'Aucune' }}</dd></div><div><dt>Visibilité</dt><dd>{{ $user->professionalProfile->visibility === 'public' ? 'Publique' : 'Privée' }} · statut professionnel {{ $user->professionalProfile->status }}</dd></div></dl>
            </section>
        @endif

        <section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Journal disponible</span><h3>Activité administrative</h3></div></div>
            @if($details['activity']->isEmpty())<p class="admin-muted">Aucune intervention administrative enregistrée pour ce compte.</p>@else
                <ol class="admin-timeline">@foreach($details['activity'] as $event)<li><strong>{{ str($event->action)->replace('.', ' ')->replace('_', ' ')->ucfirst() }}</strong><small>{{ $event->created_at?->format('d/m/Y H:i') }} · administrateur #{{ $event->user_id ?? 'inconnu' }}</small>@if($event->metadata)<p>{{ collect($event->metadata)->except(['password', 'token'])->map(fn ($value, $key) => $key.': '.(is_scalar($value) ? $value : json_encode($value)))->join(' · ') }}</p>@endif</li>@endforeach</ol>
            @endif
        </section>

        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Sécurité</span><h3>Actions sensibles</h3></div></div>
            <p class="admin-muted">Les actions modifient l’état réel du compte et sont journalisées.</p>
            @if($user->isActive())
                @can('suspend', $user)
                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="admin-action-form" data-confirm="Suspendre ce compte ? Les sessions API seront révoquées.">
                        @csrf <button class="button button--danger" type="submit"><i class="fa-solid fa-user-slash"></i> Suspendre le compte</button>
                    </form>
                @endcan
            @else
                @can('activate', $user)
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="admin-action-form" data-confirm="Réactiver ce compte ?">
                        @csrf <button class="button button--primary" type="submit"><i class="fa-solid fa-user-check"></i> Réactiver le compte</button>
                    </form>
                @endcan
            @endif
        </section>
    </div>
@endsection
