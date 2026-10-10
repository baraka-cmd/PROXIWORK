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

    @if($canManageRoles && auth()->id() !== $user->id)
        <section class="admin-card">
            <div class="admin-card__header">
                <div><span class="admin-card__eyebrow">RBAC</span><h3>Rôles et autorisations</h3></div>
            </div>
            <p class="admin-muted">Sélectionnez les rôles effectifs de ce compte. Toute modification révoque ses anciennes sessions et clés API.</p>
            <form method="POST" action="{{ route('admin.users.roles.update', $user) }}" class="admin-role-form">
                @csrf
                @method('PUT')
                @error('role_ids')<div class="admin-alert admin-alert--error" role="alert">{{ $message }}</div>@enderror
                <div class="permission-grid">
                    @foreach($assignableRoles as $role)
                        <label class="permission-item">
                            <input
                                type="checkbox"
                                name="role_ids[]"
                                value="{{ $role->id }}"
                                @checked(collect(old('role_ids', $user->roles->pluck('id')->all()))->contains(fn ($id) => (int) $id === $role->id))
                            >
                            <span>
                                <strong>{{ $role->display_name }}</strong>
                                <small>{{ $role->is_system ? 'Rôle système' : 'Rôle personnalisé' }}</small>
                                <code>{{ $role->name }}</code>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="admin-form-actions">
                    <button class="button button--primary" type="submit"><i class="fa-solid fa-shield-halved"></i> Enregistrer les rôles</button>
                </div>
            </form>
        </section>
    @endif

@endsection
