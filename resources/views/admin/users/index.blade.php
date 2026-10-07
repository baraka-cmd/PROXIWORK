@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION')
@section('page_title', 'Utilisateurs')
@section('page_description', 'Recherchez, contrôlez et sécurisez les comptes de la plateforme.')

@section('page_actions')
    <span class="admin-page-badge"><i class="fa-solid fa-shield-halved"></i> Gestion sécurisée</span>
@endsection

@section('content')
    @if(session('success'))
        <div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('success') }}</div>
    @endif

    <section class="admin-card admin-users__filters" aria-labelledby="users-filters-title">
        <div class="admin-card__header">
            <div>
                <span class="admin-card__eyebrow">Recherche</span>
                <h3 id="users-filters-title">Filtrer les comptes</h3>
            </div>
            <a class="admin-link" href="{{ route('admin.users.index') }}">Réinitialiser</a>
        </div>
        <form method="GET" class="admin-filter-grid">
            <label class="admin-field admin-field--wide">
                <span>Recherche</span>
                <div class="admin-input-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom ou adresse e-mail">
                </div>
            </label>
            <label class="admin-field">
                <span>Statut</span>
                <select name="account_status">
                    <option value="">Tous</option>
                    <option value="active" @selected(($filters['account_status'] ?? '') === 'active')>Actif</option>
                    <option value="suspended" @selected(($filters['account_status'] ?? '') === 'suspended')>Suspendu</option>
                </select>
            </label>
            <label class="admin-field">
                <span>Rôle</span>
                <input name="role" value="{{ $filters['role'] ?? '' }}" placeholder="ex. professional">
            </label>
            <label class="admin-field">
                <span>E-mail</span>
                <select name="email_verified">
                    <option value="">Tous</option>
                    <option value="1" @selected(($filters['email_verified'] ?? '') === true || ($filters['email_verified'] ?? '') === '1')>Vérifié</option>
                    <option value="0" @selected(($filters['email_verified'] ?? '') === false || ($filters['email_verified'] ?? '') === '0')>Non vérifié</option>
                </select>
            </label>
            <label class="admin-field">
                <span>Trier par</span>
                <select name="sort">
                    <option value="-created_at" @selected(($filters['sort'] ?? '-created_at') === '-created_at')>Plus récents</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Plus anciens</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nom A–Z</option>
                    <option value="-name" @selected(($filters['sort'] ?? '') === '-name')>Nom Z–A</option>
                </select>
            </label>
            <div class="admin-filter-actions">
                <button class="button button--primary" type="submit"><i class="fa-solid fa-filter"></i> Appliquer</button>
            </div>
        </form>
    </section>

    <section class="admin-card admin-users__table-card" aria-labelledby="users-table-title">
        <div class="admin-card__header">
            <div><span class="admin-card__eyebrow">Comptes</span><h3 id="users-table-title">{{ $users->total() }} utilisateur{{ $users->total() > 1 ? 's' : '' }}</h3></div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Utilisateur</th><th>Rôles</th><th>Statut</th><th>E-mail</th><th>Créé le</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><a class="admin-user-cell" href="{{ route('admin.users.show', $user) }}"><span class="admin-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span></a></td>
                        <td><div class="admin-chip-list">@forelse($user->roles as $role)<span class="admin-chip">{{ $role->display_name }}</span>@empty<span class="admin-muted">Aucun</span>@endforelse</div></td>
                        <td><span class="admin-status admin-status--{{ $user->account_status?->value }}">{{ $user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</span></td>
                        <td>@if($user->email_verified_at)<span class="admin-verified"><i class="fa-solid fa-circle-check"></i> Vérifié</span>@else<span class="admin-muted">Non vérifié</span>@endif</td>
                        <td>{{ $user->created_at?->format('d/m/Y') }}</td>
                        <td class="admin-table__actions"><a class="admin-icon-button" href="{{ route('admin.users.show', $user) }}" aria-label="Voir {{ $user->name }}"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="admin-empty"><i class="fa-solid fa-users-slash"></i><strong>Aucun utilisateur trouvé</strong><span>Modifiez vos critères de recherche.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="admin-pagination">{{ $users->links() }}</div>@endif
    </section>
@endsection
