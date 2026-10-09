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
                <div><dt>Dernière mise à jour</dt><dd>{{ $user->updated_at?->format('d/m/Y H:i') }}</dd></div>
            </dl>
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
