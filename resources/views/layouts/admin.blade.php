@extends('layouts.dashboard')

@section('body_class', 'app-shell dashboard-layout admin-layout')
@section('dashboard_eyebrow', 'CENTRE DE CONTRÔLE')
@section('dashboard_title', 'Administration')

@section('dashboard_sidebar')
    @php
        $adminGroups = [
            [
                'title' => 'Tableau de bord',
                'icon' => 'fa-gauge-high',
                'items' => [
                    ['label' => 'Accueil', 'route' => 'admin.dashboard', 'permission' => 'admin.dashboard.view', 'icon' => 'fa-house', 'active' => ['admin.dashboard']],
                ],
            ],
            [
                'title' => 'Utilisateurs',
                'icon' => 'fa-users',
                'items' => [
                    ['label' => 'Tous les utilisateurs', 'route' => 'admin.users.index', 'permission' => 'admin.users.view', 'icon' => 'fa-users', 'active' => ['admin.users.*'], 'default' => true],
                    ['label' => 'Clients', 'route' => 'admin.users.index', 'permission' => 'admin.users.view', 'icon' => 'fa-user-group', 'active' => ['admin.users.index'], 'query' => ['role' => 'client']],
                    ['label' => 'Prestataires professionnels', 'route' => 'admin.professionals.index', 'permission' => 'admin.professionals.view', 'icon' => 'fa-user-tie', 'active' => ['admin.professionals.*']],
                    ['label' => 'Comptes suspendus', 'route' => 'admin.users.index', 'permission' => 'admin.users.view', 'icon' => 'fa-user-lock', 'active' => ['admin.users.index'], 'query' => ['account_status' => 'suspended']],
                    ['label' => 'Gestion des accès', 'route' => 'admin.rbac.dashboard', 'permission' => 'rbac.view', 'icon' => 'fa-user-shield', 'active' => ['admin.rbac.*']],
                    ['label' => 'Rôles', 'route' => 'admin.roles.index', 'permission' => 'rbac.view', 'icon' => 'fa-users-gear', 'active' => ['admin.roles.*']],
                    ['label' => 'Permissions', 'route' => 'admin.permissions.index', 'permission' => 'rbac.view', 'icon' => 'fa-key', 'active' => ['admin.permissions.*']],
                ],
            ],
            [
                'title' => 'Vérifications',
                'icon' => 'fa-user-check',
                'items' => [
                    ['label' => 'Dossiers professionnels', 'route' => 'admin.verification.index', 'permission' => 'admin.professionals.view', 'icon' => 'fa-folder-open', 'active' => ['admin.verification.*']],
                ],
            ],
            [
                'title' => 'Catalogue',
                'icon' => 'fa-layer-group',
                'items' => [
                    ['label' => 'Catégories', 'route' => 'admin.categories.index', 'permission' => 'categories.view', 'icon' => 'fa-layer-group', 'active' => ['admin.categories.*']],
                    ['label' => 'Compétences', 'route' => 'admin.skills.index', 'permission' => 'categories.manage', 'icon' => 'fa-list-check', 'active' => ['admin.skills.*']],
                    ['label' => 'Services', 'route' => 'admin.services.index', 'permission' => 'admin.services.view', 'icon' => 'fa-briefcase', 'active' => ['admin.services.*']],
                ],
            ],
            [
                'title' => 'Opérations',
                'icon' => 'fa-arrows-rotate',
                'items' => [
                    ['label' => 'Demandes de services', 'route' => 'admin.service-requests.index', 'permission' => 'requests.view', 'icon' => 'fa-file-lines', 'active' => ['admin.service-requests.*']],
                    ['label' => 'Commandes', 'route' => 'admin.orders.index', 'permission' => 'orders.view', 'icon' => 'fa-cart-shopping', 'active' => ['admin.orders.*']],
                ],
            ],
            [
                'title' => 'Finances',
                'icon' => 'fa-wallet',
                'items' => [
                    ['label' => 'Vue générale', 'route' => 'admin.finance.overview', 'permission' => 'finance.view', 'icon' => 'fa-chart-pie', 'active' => ['admin.finance.overview']],
                    ['label' => 'Paiements', 'route' => 'admin.payments.index', 'permission' => 'payments.view', 'icon' => 'fa-credit-card', 'active' => ['admin.payments.*']],
                    ['label' => 'Transactions', 'route' => 'admin.finance.transactions', 'permission' => 'transactions.view', 'icon' => 'fa-arrow-right-arrow-left', 'active' => ['admin.finance.transactions*']],
                    ['label' => 'Commissions', 'route' => 'admin.finance.commissions', 'permission' => 'commissions.view', 'icon' => 'fa-percent', 'active' => ['admin.finance.commissions*']],
                    ['label' => 'Portefeuilles professionnels', 'route' => 'admin.finance.wallets', 'permission' => 'wallets.view', 'icon' => 'fa-wallet', 'active' => ['admin.finance.wallets*']],
                    ['label' => 'Retraits', 'route' => 'admin.withdrawals.index', 'permission' => 'admin.withdrawals.manage', 'icon' => 'fa-money-bill-transfer', 'active' => ['admin.withdrawals.*']],
                    ['label' => 'Remboursements', 'route' => 'admin.finance.refunds', 'permission' => 'refunds.view', 'icon' => 'fa-rotate-left', 'active' => ['admin.finance.refunds*']],
                    ['label' => 'Mouvements financiers', 'route' => 'admin.finance.movements', 'permission' => 'financial_movements.view', 'icon' => 'fa-list-ol', 'active' => ['admin.finance.movements*']],
                    ['label' => 'Rapports financiers', 'route' => 'admin.finance.reports', 'permission' => 'financial_reports.view', 'icon' => 'fa-chart-column', 'active' => ['admin.finance.reports*']],
                ],
            ],
            [
                'title' => 'Modération et litiges',
                'icon' => 'fa-flag',
                'items' => [
                    ['label' => 'Signalements', 'route' => 'admin.reports.index', 'permission' => 'reports.manage', 'icon' => 'fa-flag', 'active' => ['admin.reports.*']],
                    ['label' => 'Support et réclamations', 'route' => 'admin.support.index', 'permission' => 'support.manage', 'icon' => 'fa-headset', 'active' => ['admin.support.*']],
                    ['label' => 'Litiges commerciaux', 'route' => 'admin.disputes.index', 'permission' => 'disputes.view', 'icon' => 'fa-scale-balanced', 'active' => ['admin.disputes.*']],
                    ['label' => 'Avis et évaluations', 'route' => 'admin.moderation.reviews', 'permission' => 'reviews.moderate', 'icon' => 'fa-star', 'active' => ['admin.moderation.reviews*']],
                    ['label' => 'Avis signalés', 'route' => 'admin.moderation.reported-reviews', 'permission' => 'reports.manage', 'icon' => 'fa-flag', 'active' => ['admin.moderation.reported-reviews']],
                    ['label' => 'Historique de modération', 'route' => 'admin.moderation.history', 'permission' => 'moderation.history.view', 'icon' => 'fa-clock-rotate-left', 'active' => ['admin.moderation.history']],
                    ['label' => 'Résolutions et décisions', 'route' => 'admin.moderation.resolutions', 'permission' => 'moderation.history.view', 'icon' => 'fa-scale-balanced', 'active' => ['admin.moderation.resolutions']],
                ],
            ],
            [
                'title' => 'Rapports et statistiques',
                'icon' => 'fa-chart-column',
                'items' => [
                    ['label' => 'Statistiques générales', 'route' => 'admin.analytics', 'permission' => 'admin.dashboard.view', 'icon' => 'fa-chart-column', 'active' => ['admin.analytics']],
                ],
            ],
            [
                'title' => 'Audit et sécurité',
                'icon' => 'fa-shield-halved',
                'items' => [
                    ['label' => 'Journal des activités', 'route' => 'admin.audit.index', 'permission' => 'audit.view', 'icon' => 'fa-clock-rotate-left', 'active' => ['admin.audit.*']],
                ],
            ],
        ];

        $adminUser = auth()->user();

        $isAdminItemActive = static function (array $item): bool {
            $routePatterns = $item['active'] ?? [$item['route']];
            $routeMatches = collect($routePatterns)->contains(
                static fn (string $pattern): bool => request()->routeIs($pattern),
            );

            if (! $routeMatches) {
                return false;
            }

            foreach (($item['query'] ?? []) as $key => $value) {
                if ((string) request()->query($key) !== (string) $value) {
                    return false;
                }
            }

            if (($item['default'] ?? false) === true) {
                return request()->query('role') === null
                    && request()->query('account_status') === null;
            }

            return true;
        };
    @endphp

    <nav class="admin-sidebar-nav" aria-label="Navigation administration" data-admin-navigation>
        <button
            class="admin-sidebar-pin"
            type="button"
            data-admin-pin
            aria-pressed="false"
            title="Épingler la barre développée"
            aria-label="Épingler la barre développée"
        >
            <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
            <span>Épingler la barre</span>
        </button>

        @foreach ($adminGroups as $group)
            @php
                $visibleItems = array_values(array_filter(
                    $group['items'],
                    static fn (array $item): bool => Route::has($item['route'])
                        && ($adminUser?->hasPermissionTo($item['permission']) ?? false),
                ));

                $isActive = collect($visibleItems)->contains($isAdminItemActive);
            @endphp

            @if ($visibleItems !== [])
                <section class="admin-nav-group {{ $isActive ? 'is-active' : '' }}" data-admin-group>
                    <button
                        class="admin-nav-group__toggle"
                        type="button"
                        aria-expanded="{{ $isActive ? 'true' : 'false' }}"
                        aria-controls="admin-nav-group-{{ $loop->index }}"
                        data-admin-group-toggle
                        title="{{ $group['title'] }}"
                    >
                        <i class="fa-solid {{ $group['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $group['title'] }}</span>
                        <i class="fa-solid fa-chevron-down admin-nav-group__chevron" aria-hidden="true"></i>
                    </button>

                    <div
                        class="admin-nav-group__items"
                        id="admin-nav-group-{{ $loop->index }}"
                        @if (! $isActive) hidden @endif
                    >
                        @foreach ($visibleItems as $item)
                            @php($itemActive = $isAdminItemActive($item))
                            <a
                                class="dashboard-nav-link admin-nav-link {{ $itemActive ? 'is-active' : '' }}"
                                href="{{ route($item['route'], $item['query'] ?? []) }}"
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
        <button type="submit" class="admin-logout-button">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span>Déconnexion</span>
        </button>
    </form>
@endsection
