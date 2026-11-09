@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION')
@section('page_title', 'Tous les utilisateurs')
@section('page_description', 'Supervisez les comptes, identifiez les situations à traiter et accédez à chaque fiche utilisateur.')

@section('page_actions')
    <span class="admin-page-badge"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Gestion sécurisée des comptes</span>
@endsection

@section('content')
    @if(session('success'))
        <div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="admin-alert admin-alert--error" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <section class="admin-card admin-users__overview" aria-labelledby="users-overview-title">
        <div class="admin-card__header">
            <div>
                <span class="admin-card__eyebrow">Vue globale</span>
                <h3 id="users-overview-title">Indicateurs des comptes</h3>
                <p>Les indicateurs respectent la recherche et les filtres actifs. Chaque carte neutralise uniquement son propre critère pour comparer les segments.</p>
            </div>
        </div>
        <div class="admin-users__stats">
            @foreach([
                ['Tous les comptes', 'total', ['role' => null, 'account_status' => null], 'fa-users'],
                ['Clients', 'clients', ['role' => 'client'], 'fa-user'],
                ['Professionnels', 'professionals', ['role' => 'professional'], 'fa-briefcase'],
                ['Comptes actifs', 'active', ['account_status' => 'active'], 'fa-circle-check'],
                ['Comptes suspendus', 'suspended', ['account_status' => 'suspended'], 'fa-user-slash'],
                ['Nouveaux · 30 jours', 'new', ['period' => '30d'], 'fa-user-plus'],
            ] as [$label, $key, $query, $icon])
                <a class="admin-users__stat" href="{{ route('admin.users.index', array_merge(request()->query(), $query, ['page' => null])) }}">
                    <span class="admin-users__stat-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span>
                    <span class="admin-users__stat-label">{{ $label }}</span>
                    <strong>{{ number_format($overview[$key]) }}</strong>
                    <span class="admin-users__stat-hint">Voir les comptes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="admin-card admin-users__filters" aria-labelledby="users-filters-title">
        <div class="admin-card__header">
            <div>
                <span class="admin-card__eyebrow">Recherche multicritère</span>
                <h3 id="users-filters-title">Retrouver un compte</h3>
                <p>Combinez les critères pour réduire précisément la liste.</p>
            </div>
            <a class="admin-link" href="{{ route('admin.users.index') }}"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Réinitialiser</a>
        </div>
        <form method="GET" action="{{ route('admin.users.index') }}" class="admin-filter-grid admin-users__filter-grid">
            <label class="admin-field admin-field--wide">
                <span>Recherche</span>
                <div class="admin-input-icon">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $canViewSensitive ? 'Nom, prénom, e-mail, téléphone ou ID' : 'Nom, prénom ou ID' }}" autocomplete="off">
                </div>
            </label>
            <label class="admin-field">
                <span>Type de compte</span>
                <select name="role">
                    <option value="">Tous les rôles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ $role->display_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>État du compte</span>
                <select name="account_status">
                    <option value="">Tous les états</option>
                    <option value="active" @selected(($filters['account_status'] ?? '') === 'active')>Actif</option>
                    <option value="suspended" @selected(($filters['account_status'] ?? '') === 'suspended')>Suspendu</option>
                </select>
            </label>
            <label class="admin-field">
                <span>Vérification de l’e-mail</span>
                <select name="email_verified">
                    <option value="">Tous</option>
                    <option value="1" @selected(($filters['email_verified'] ?? null) === true)>Vérifié</option>
                    <option value="0" @selected(($filters['email_verified'] ?? null) === false)>Non vérifié</option>
                </select>
            </label>
            <label class="admin-field">
                <span>Vérification professionnelle</span>
                <select name="verification_status">
                    <option value="">Tous les états</option>
                    <option value="pending" @selected(($filters['verification_status'] ?? '') === 'pending')>En attente</option>
                    <option value="under_review" @selected(($filters['verification_status'] ?? '') === 'under_review')>En examen</option>
                    <option value="verified" @selected(($filters['verification_status'] ?? '') === 'verified')>Vérifié</option>
                    <option value="rejected" @selected(($filters['verification_status'] ?? '') === 'rejected')>Refusé</option>
                </select>
            </label>
            <label class="admin-field">
                <span>Inscription</span>
                <select name="period">
                    <option value="">Toutes les dates</option>
                    <option value="today" @selected(($filters['period'] ?? '') === 'today')>Aujourd’hui</option>
                    <option value="7d" @selected(($filters['period'] ?? '') === '7d')>7 derniers jours</option>
                    <option value="30d" @selected(($filters['period'] ?? '') === '30d')>30 derniers jours</option>
                </select>
            </label>
            <label class="admin-field">
                <span>Inscription à partir du</span>
                <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </label>
            <label class="admin-field">
                <span>Inscription jusqu’au</span>
                <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
            </label>
            <label class="admin-field">
                <span>{{ $canViewSensitive ? 'Localisation du compte' : 'Localisation professionnelle publique' }}</span>
                <input name="location" value="{{ $filters['location'] ?? '' }}" placeholder="{{ $canViewSensitive ? 'Ville, commune, quartier ou province' : 'Ville, commune ou province professionnelle' }}">
            </label>
            @if($activityTrackingAvailable)
                <label class="admin-field">
                    <span>Activité de session web</span>
                    <select name="activity">
                        <option value="">Toutes les activités</option>
                        <option value="recent_7d" @selected(($filters['activity'] ?? '') === 'recent_7d')>Session connue · 7 derniers jours</option>
                        <option value="inactive_30d" @selected(($filters['activity'] ?? '') === 'inactive_30d')>Aucune session · 30 derniers jours</option>
                    </select>
                    <small>Cette donnée concerne les sessions web enregistrées, pas les connexions API.</small>
                </label>
            @endif
            <label class="admin-field">
                <span>Trier par</span>
                <select name="sort">
                    <option value="-created_at" @selected(($filters['sort'] ?? '-created_at') === '-created_at')>Inscription · plus récent</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Inscription · plus ancien</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nom A–Z</option>
                    <option value="-name" @selected(($filters['sort'] ?? '') === '-name')>Nom Z–A</option>
                </select>
            </label>
            <label class="admin-field">
                <span>Résultats par page</span>
                <select name="per_page">
                    @foreach([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>
            <div class="admin-filter-actions">
                <button class="button button--primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Appliquer les filtres</button>
            </div>
        </form>
    </section>

    <section class="admin-card admin-users__table-card" aria-labelledby="users-table-title">
        <div class="admin-card__header">
            <div>
                <span class="admin-card__eyebrow">Répertoire des comptes</span>
                <h3 id="users-table-title">{{ number_format($users->total()) }} utilisateur{{ $users->total() > 1 ? 's' : '' }}</h3>
                <p>Affichage de {{ $users->firstItem() ?? 0 }} à {{ $users->lastItem() ?? 0 }} sur {{ $users->total() }} résultat(s).</p>
            </div>
        </div>

        <div class="admin-table-wrap admin-users__desktop-table">
            <table class="admin-table admin-users__table">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Téléphone</th>
                        <th>Vérification</th>
                        <th>Statut</th>
                        <th>Inscription</th>
                        @if($activityTrackingAvailable)<th>Dernière session web</th>@endif
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <a class="admin-user-cell" href="{{ route('admin.users.show', array_merge(['user' => $user], request()->query())) }}">
                                    <span class="admin-avatar">{{ mb_strtoupper(mb_substr($user->profile?->first_name ?: $user->name, 0, 1).mb_substr($user->profile?->last_name ?? '', 0, 1)) }}</span>
                                    <span><strong>{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name }}</strong><small>{{ $canViewSensitive ? $user->email : 'E-mail masqué' }}</small></span>
                                </a>
                            </td>
                            <td>#{{ $user->id }}</td>
                            <td><div class="admin-chip-list">@forelse($user->roles as $role)<span class="admin-chip">{{ $role->display_name }}</span>@empty<span class="admin-muted">Aucun rôle</span>@endforelse</div></td>
                            <td>{{ $canViewSensitive ? ($user->profile?->phone ?: 'Non renseigné') : 'Masqué · permission requise' }}</td>
                            <td>
                                <div class="admin-users__verification">
                                    @if($user->email_verified_at)<span class="admin-verified"><i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i> E-mail vérifié</span>@else<span class="admin-muted">E-mail non vérifié</span>@endif
                                    @if($user->professionalProfile)
                                        @php($professionalVerification = $user->professionalProfile->verification_status?->value)
                                        <small>Professionnel : {{ match($professionalVerification) { 'pending' => 'En attente', 'under_review' => 'En examen', 'verified' => 'Vérifié', 'rejected' => 'Refusé', default => 'Non défini' } }}</small>
                                    @endif
                                </div>
                            </td>
                            <td><span class="admin-status admin-status--{{ $user->account_status?->value }}">{{ $user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</span></td>
                            <td>{{ $user->created_at?->format('d/m/Y') }}</td>
                            @if($activityTrackingAvailable)
                                <td>
                                    @if($user->last_activity_timestamp)
                                        {{ \Illuminate\Support\Carbon::createFromTimestamp((int) $user->last_activity_timestamp)->format('d/m/Y H:i') }}
                                    @else
                                        <span class="admin-muted">Aucune session connue</span>
                                    @endif
                                </td>
                            @endif
                            <td class="admin-table__actions"><a class="admin-icon-button" href="{{ route('admin.users.show', array_merge(['user' => $user], request()->query())) }}" aria-label="Consulter le compte #{{ $user->id }}"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $activityTrackingAvailable ? 9 : 8 }}"><div class="admin-empty"><i class="fa-solid fa-users-slash" aria-hidden="true"></i><strong>Aucun utilisateur trouvé</strong><span>Modifiez ou réinitialisez vos critères de recherche.</span><a class="admin-link" href="{{ route('admin.users.index') }}">Effacer les filtres</a></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-users__mobile-list">
            @forelse($users as $user)
                <article class="admin-users__mobile-card">
                    <div class="admin-users__mobile-head">
                        <a class="admin-user-cell" href="{{ route('admin.users.show', array_merge(['user' => $user], request()->query())) }}">
                            <span class="admin-avatar">{{ mb_strtoupper(mb_substr($user->profile?->first_name ?: $user->name, 0, 1).mb_substr($user->profile?->last_name ?? '', 0, 1)) }}</span>
                            <span><strong>{{ trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->name }}</strong><small>{{ $canViewSensitive ? $user->email : 'E-mail masqué · permission requise' }}</small></span>
                        </a>
                        <span class="admin-status admin-status--{{ $user->account_status?->value }}">{{ $user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</span>
                    </div>
                    <div class="admin-chip-list">@forelse($user->roles as $role)<span class="admin-chip">{{ $role->display_name }}</span>@empty<span class="admin-muted">Aucun rôle</span>@endforelse</div>
                    <dl class="admin-users__mobile-meta">
                        <div><dt>Identifiant</dt><dd>#{{ $user->id }}</dd></div>
                        <div><dt>Téléphone</dt><dd>{{ $canViewSensitive ? ($user->profile?->phone ?: 'Non renseigné') : 'Masqué · permission requise' }}</dd></div>
                        <div><dt>Inscription</dt><dd>{{ $user->created_at?->format('d/m/Y') }}</dd></div>
                        <div><dt>E-mail</dt><dd>{{ $user->email_verified_at ? 'Vérifié' : 'Non vérifié' }}</dd></div>
                        @if($activityTrackingAvailable)
                            <div><dt>Dernière session web</dt><dd>{{ $user->last_activity_timestamp ? \Illuminate\Support\Carbon::createFromTimestamp((int) $user->last_activity_timestamp)->format('d/m/Y H:i') : 'Non connue' }}</dd></div>
                        @endif
                    </dl>
                    <a class="button button--secondary admin-users__mobile-action" href="{{ route('admin.users.show', array_merge(['user' => $user], request()->query())) }}">Consulter la fiche <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>
            @empty
                <div class="admin-empty"><i class="fa-solid fa-users-slash" aria-hidden="true"></i><strong>Aucun utilisateur trouvé</strong><span>Modifiez ou réinitialisez vos critères de recherche.</span></div>
            @endforelse
        </div>

        @if($users->hasPages())
            <div class="admin-pagination">{{ $users->links() }}</div>
        @endif
    </section>
@endsection
