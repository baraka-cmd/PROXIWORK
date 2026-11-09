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

    <section class="admin-card" aria-labelledby="users-overview-title">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Vue globale</span><h3 id="users-overview-title">Comptes PROXIWORK</h3></div></div>
        <div class="admin-stat-grid">
            @foreach([
                ['Tous les comptes', 'total', []],
                ['Clients', 'clients', ['role' => 'client']],
                ['Professionnels', 'professionals', ['role' => 'professional']],
                ['Comptes actifs', 'active', ['account_status' => 'active']],
                ['Comptes suspendus', 'suspended', ['account_status' => 'suspended']],
                ['Nouveaux · 30 jours', 'new', ['period' => '30d']],
            ] as [$label, $key, $query])
                <a class="admin-card admin-stat-card" href="{{ route('admin.users.index', array_merge(request()->query(), $query, ['page' => null])) }}">
                    <span>{{ $label }}</span><strong>{{ number_format($overview[$key]) }}</strong>
                </a>
            @endforeach
        </div>
    </section>

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
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, e-mail, téléphone ou identifiant">
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
                <select name="role"><option value="">Tous les rôles</option><option value="client" @selected(($filters['role'] ?? '') === 'client')>Client</option><option value="professional" @selected(($filters['role'] ?? '') === 'professional')>Professionnel</option><option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Administrateur</option><option value="support" @selected(($filters['role'] ?? '') === 'support')>Support</option><option value="moderator" @selected(($filters['role'] ?? '') === 'moderator')>Modérateur</option></select>
            </label>
            <label class="admin-field"><span>Inscription</span><select name="period"><option value="">Toutes les dates</option><option value="today" @selected(($filters['period'] ?? '') === 'today')>Aujourd’hui</option><option value="7d" @selected(($filters['period'] ?? '') === '7d')>7 derniers jours</option><option value="30d" @selected(($filters['period'] ?? '') === '30d')>30 derniers jours</option></select></label>
            <label class="admin-field"><span>Localisation</span><input name="location" value="{{ $filters['location'] ?? '' }}" placeholder="Ville, commune ou province"></label>
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
                <thead><tr><th>Utilisateur</th><th>ID</th><th>Type</th><th>Contact</th><th>Vérification</th><th>Statut</th><th>Inscription</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><a class="admin-user-cell" href="{{ route('admin.users.show', array_merge(['user' => $user], request()->query())) }}"><span class="admin-avatar">{{ mb_strtoupper(mb_substr($user->profile?->first_name ?: $user->name, 0, 1).mb_substr($user->profile?->last_name ?? '', 0, 1)) }}</span><span><strong>{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name }}</strong><small>{{ $user->email }}</small></span></a></td>
                        <td>#{{ $user->id }}</td>
                        <td><div class="admin-chip-list">@forelse($user->roles as $role)<span class="admin-chip">{{ $role->display_name }}</span>@empty<span class="admin-muted">Aucun</span>@endforelse</div></td>
                        <td>{{ $user->email }}<small>{{ $user->profile?->phone ?: 'Téléphone non renseigné' }}</small></td>
                        <td>@if($user->email_verified_at)<span class="admin-verified">E-mail vérifié</span>@else<span class="admin-muted">E-mail non vérifié</span>@endif</td>
                        <td><span class="admin-status admin-status--{{ $user->account_status?->value }}">{{ $user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</span></td>
                        <td>{{ $user->created_at?->format('d/m/Y') }}</td>
                        <td class="admin-table__actions"><a class="admin-icon-button" href="{{ route('admin.users.show', $user) }}" aria-label="Voir {{ $user->name }}"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="admin-empty"><i class="fa-solid fa-users-slash"></i><strong>Aucun utilisateur trouvé</strong><span>Modifiez vos critères de recherche.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="admin-pagination">{{ $users->links() }}</div>@endif
    </section>
@endsection
