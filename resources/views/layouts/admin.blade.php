@extends('layouts.dashboard')

@section('body_class', 'app-shell dashboard-layout admin-layout')
@section('dashboard_eyebrow', 'CENTRE DE CONTRÔLE')
@section('dashboard_title', 'Administration')

@section('dashboard_sidebar')
    <nav class="admin-sidebar-nav" aria-label="Navigation administration" data-admin-navigation>
        <button
            class="admin-sidebar-pin"
            type="button"
            data-admin-pin
            aria-pressed="false"
            title="Épingler la barre"
            aria-label="Épingler la barre développée"
        >
            <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
            <span>Épingler la barre</span>
        </button>

        @php
            /*
             * Ne déclarer ici que les destinations qui existent réellement.
             * Les permissions serveur restent contrôlées par les middlewares Laravel.
             */
            $adminGroups = [
                [
                    'title' => 'Tableau de bord',
                    'icon' => 'fa-gauge-high',
                    'items' => [
                        ['label' => 'Accueil', 'route' => 'admin.dashboard', 'permission' => 'admin.dashboard.view', 'icon' => 'fa-house', 'exact' => true],
                    ],
                ],
                [
                    'title' => 'Utilisateurs',
                    'icon' => 'fa-users',
                    'items' => [
                        ['label' => 'Tous les utilisateurs', 'route' => 'admin.users.index', 'active_query' => ['role' => null, 'account_status' => null], 'permission' => 'admin.users.view', 'icon' => 'fa-users'],
                        ['label' => 'Clients', 'route' => 'admin.users.index', 'parameters' => ['role' => 'client'], 'active_query' => ['role' => 'client', 'account_status' => null], 'permission' => 'admin.users.view', 'icon' => 'fa-user-group'],
                        ['label' => 'Professionnels', 'route' => 'admin.professionals.index', 'permission' => 'admin.professionals.view', 'icon' => 'fa-user-tie'],
                        ['label' => 'Comptes suspendus', 'route' => 'admin.users.index', 'parameters' => ['account_status' => 'suspended'], 'active_query' => ['role' => null, 'account_status' => 'suspended'], 'permission' => 'admin.users.view', 'icon' => 'fa-user-lock'],
                        ['label' => 'Rôles', 'route' => 'admin.roles.index', 'permission' => 'rbac.view', 'icon' => 'fa-user-shield'],
                        ['label' => 'Permissions', 'route' => 'admin.permissions.index', 'permission' => 'rbac.view', 'icon' => 'fa-key'],
                    ],
                ],
                [
                    'title' => 'Vérifications',
                    'icon' => 'fa-user-check',
                    'items' => [
                        ['label' => 'Dossiers professionnels', 'route' => 'admin.verification.index', 'active_query' => ['verification_status' => null], 'permission' => 'admin.professionals.view', 'icon' => 'fa-folder-open'],
                        ['label' => 'Dossiers en attente', 'route' => 'admin.verification.index', 'parameters' => ['verification_status' => 'pending'], 'active_query' => ['verification_status' => 'pending'], 'permission' => 'admin.professionals.view', 'icon' => 'fa-hourglass-half'],
                    ],
                ],
                [
                    'title' => 'Catalogue',
                    'icon' => 'fa-layer-group',
                    'items' => [
                        ['label' => 'Catégories', 'route' => 'admin.categories.index', 'permission' => 'categories.view', 'icon' => 'fa-layer-group'],
                        ['label' => 'Compétences', 'route' => 'admin.skills.index', 'permission' => 'categories.manage', 'icon' => 'fa-list-check'],
                        ['label' => 'Tous les services', 'route' => 'admin.services.index', 'active_query' => ['status' => null], 'permission' => 'admin.services.view', 'icon' => 'fa-briefcase'],
                        ['label' => 'Services publiés', 'route' => 'admin.services.index', 'parameters' => ['status' => 'published'], 'active_query' => ['status' => 'published'], 'permission' => 'admin.services.view', 'icon' => 'fa-circle-check'],
                    ],
                ],
                [
                    'title' => 'Opérations',
                    'icon' => 'fa-arrows-rotate',
                    'items' => [
                        ['label' => 'Demandes de services', 'route' => 'admin.service-requests.index', 'permission' => 'requests.view', 'icon' => 'fa-file-lines'],
                        ['label' => 'Commandes', 'route' => 'admin.orders.index', 'permission' => 'orders.view', 'icon' => 'fa-cart-shopping'],
                    ],
                ],
                [
                    'title' => 'Finances',
                    'icon' => 'fa-wallet',
                    'items' => [
                        ['label' => 'Paiements et transactions', 'route' => 'admin.payments.index', 'permission' => 'payments.view', 'icon' => 'fa-credit-card'],
                        ['label' => 'Retraits', 'route' => 'admin.withdrawals.index', 'permission' => 'admin.withdrawals.manage', 'icon' => 'fa-money-bill-transfer'],
                    ],
                ],
                [
                    'title' => 'Modération et litiges',
                    'icon' => 'fa-shield-halved',
                    'items' => [
                        ['label' => 'Signalements', 'route' => 'admin.reports.index', 'permission' => 'reports.manage', 'icon' => 'fa-flag'],
                        ['label' => 'Support et réclamations', 'route' => 'admin.support.index', 'permission' => 'support.manage', 'icon' => 'fa-headset'],
                    ],
                ],
                [
                    'title' => 'Rapports et statistiques',
                    'icon' => 'fa-chart-column',
                    'items' => [
                        ['label' => 'Statistiques générales', 'route' => 'admin.analytics', 'permission' => 'admin.dashboard.view', 'icon' => 'fa-chart-column', 'exact' => true],
                    ],
                ],
                [
                    'title' => 'Audit et sécurité',
                    'icon' => 'fa-shield-halved',
                    'items' => [
                        ['label' => 'Journal des activités', 'route' => 'admin.audit.index', 'permission' => 'audit.view', 'icon' => 'fa-clock-rotate-left'],
                    ],
                ],
            ];
        @endphp

        @foreach ($adminGroups as $group)
            @php
                $visibleItems = array_filter(
                    $group['items'],
                    fn (array $item): bool => auth()->user()?->hasPermissionTo($item['permission'])
                        && Route::has($item['route']),
                );

                $isActive = collect($visibleItems)->contains(
                    fn (array $item): bool => request()->routeIs($item['route'])
                        || (! ($item['exact'] ?? false) && request()->routeIs(str_replace('.index', '.*', $item['route']))),
                );

                $groupId = 'admin-nav-group-'.$loop->index;
            @endphp

            @if ($visibleItems !== [])
                <section class="admin-nav-group {{ $isActive ? 'is-active' : '' }}" data-admin-group>
                    <button
                        class="admin-nav-group__toggle"
                        type="button"
                        aria-expanded="{{ $isActive ? 'true' : 'false' }}"
                        aria-controls="{{ $groupId }}"
                        data-admin-group-toggle
                        title="{{ $group['title'] }}"
                    >
                        <i class="fa-solid {{ $group['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $group['title'] }}</span>
                        <i class="fa-solid fa-chevron-down admin-nav-group__chevron" aria-hidden="true"></i>
                    </button>

                    <div id="{{ $groupId }}" class="admin-nav-group__items" @if (! $isActive) hidden @endif>
                        @foreach ($visibleItems as $item)
                            @php
                                $itemMatchesRoute = request()->routeIs($item['route'])
                                    || (! ($item['exact'] ?? false) && request()->routeIs(str_replace('.index', '.*', $item['route'])));
                                $queryMatches = collect($item['active_query'] ?? [])->every(
                                    fn ($value, $key): bool => request()->query($key) === $value,
                                );
                                $itemActive = $itemMatchesRoute && $queryMatches;
                            @endphp

                            <a
                                class="dashboard-nav-link admin-nav-link {{ $itemActive ? 'is-active' : '' }}"
                                href="{{ route($item['route'], $item['parameters'] ?? []) }}"
                                title="{{ $item['label'] }}"
                                @if ($itemActive) aria-current="page" @endif
                            >
                                <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </nav>
@endsection

@section('dashboard_sidebar_footer')
    <div class="dashboard-security-note">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <span>Administration protégée</span>
    </div>

    <form method="POST" action="{{ route('admin.logout') }}" class="admin-logout-form">
        @csrf
        <button type="submit" class="admin-logout-button" title="Déconnexion">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>Déconnexion</span>
        </button>
    </form>
@endsection
