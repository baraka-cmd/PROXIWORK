@extends('layouts.dashboard')

@section('body_class', 'app-shell dashboard-layout admin-layout')
@section('dashboard_eyebrow', 'CENTRE DE CONTRÔLE')
@section('dashboard_title', 'Administration')

@section('dashboard_sidebar')
    <nav class="admin-sidebar-nav" aria-label="Navigation administration" data-admin-navigation>
        <button class="admin-sidebar-pin" type="button" data-admin-pin aria-pressed="false" title="Épingler la barre" aria-label="Épingler la barre développée">
            <i class="fa-solid fa-thumbtack" aria-hidden="true"></i><span>Épingler la barre</span>
        </button>
        @php
            $adminGroups = [
                ['title' => 'Tableau de bord', 'icon' => 'fa-gauge-high', 'items' => [
                    ['label' => 'Accueil', 'route' => 'admin.dashboard', 'permission' => 'admin.dashboard.view', 'icon' => 'fa-house', 'exact' => true],
                ]],
                ['title' => 'Rapports et statistiques', 'icon' => 'fa-chart-column', 'items' => [
                    ['label' => 'Statistiques générales', 'route' => 'admin.analytics', 'permission' => 'admin.dashboard.view', 'icon' => 'fa-chart-column'],
                ]],
                ['title' => 'Utilisateurs', 'icon' => 'fa-users', 'items' => [
                    ['label' => 'Tous les utilisateurs', 'route' => 'admin.users.index', 'permission' => 'admin.users.view', 'icon' => 'fa-users'],
                    ['label' => 'Professionnels', 'route' => 'admin.professionals.index', 'permission' => 'admin.professionals.view', 'icon' => 'fa-user-tie'],
                    ['label' => 'Rôles', 'route' => 'admin.roles.index', 'permission' => 'rbac.view', 'icon' => 'fa-user-shield'],
                    ['label' => 'Permissions', 'route' => 'admin.permissions.index', 'permission' => 'rbac.view', 'icon' => 'fa-key'],
                ]],
                ['title' => 'Vérifications', 'icon' => 'fa-user-check', 'items' => [
                    ['label' => 'Dossiers professionnels', 'route' => 'admin.verification.index', 'permission' => 'admin.professionals.view', 'icon' => 'fa-folder-open'],
                ]],
                ['title' => 'Catalogue', 'icon' => 'fa-layer-group', 'items' => [
                    ['label' => 'Catégories', 'route' => 'admin.categories.index', 'permission' => 'categories.view', 'icon' => 'fa-layer-group'],
                    ['label' => 'Compétences', 'route' => 'admin.skills.index', 'permission' => 'categories.manage', 'icon' => 'fa-list-check'],
                    ['label' => 'Services', 'route' => 'admin.services.index', 'permission' => 'admin.services.view', 'icon' => 'fa-briefcase'],
                    ['label' => 'Preuves de réalisation', 'route' => 'admin.service-realizations.file', 'permission' => 'admin.services.view', 'icon' => 'fa-images', 'hidden' => true],
                ]],
                ['title' => 'Opérations', 'icon' => 'fa-arrows-rotate', 'items' => [
                    ['label' => 'Demandes de services', 'route' => 'admin.service-requests.index', 'permission' => 'requests.view', 'icon' => 'fa-file-lines'],
                    ['label' => 'Commandes', 'route' => 'admin.orders.index', 'permission' => 'orders.view', 'icon' => 'fa-cart-shopping'],
                ]],
                ['title' => 'Finances', 'icon' => 'fa-wallet', 'items' => [
                    ['label' => 'Paiements et transactions', 'route' => 'admin.payments.index', 'permission' => 'payments.view', 'icon' => 'fa-credit-card'],
                    ['label' => 'Retraits', 'route' => 'admin.withdrawals.index', 'permission' => 'admin.withdrawals.manage', 'icon' => 'fa-money-bill-transfer'],
                ]],
                ['title' => 'Modération et litiges', 'icon' => 'fa-flag', 'items' => [
                    ['label' => 'Signalements', 'route' => 'admin.reports.index', 'permission' => 'reports.manage', 'icon' => 'fa-flag'],
                    ['label' => 'Support et réclamations', 'route' => 'admin.support.index', 'permission' => 'support.manage', 'icon' => 'fa-headset'],
                ]],
                ['title' => 'Communication', 'icon' => 'fa-bullhorn', 'items' => []],
                ['title' => 'Audit et sécurité', 'icon' => 'fa-shield-halved', 'items' => [
                    ['label' => 'Journal des activités', 'route' => 'admin.audit.index', 'permission' => 'audit.view', 'icon' => 'fa-clock-rotate-left'],
                ]],
                ['title' => 'Configuration', 'icon' => 'fa-sliders', 'items' => []],
            ];
        @endphp
        @foreach ($adminGroups as $group)
            @php
                $visibleItems = array_filter($group['items'], fn (array $item): bool => auth()->user()?->hasPermissionTo($item['permission']) && Route::has($item['route']) && ! ($item['hidden'] ?? false));
                $isActive = collect($visibleItems)->contains(fn (array $item): bool => request()->routeIs($item['route']) || (! ($item['exact'] ?? false) && request()->routeIs(str_replace('.index', '.*', $item['route']))));
            @endphp
            @if ($visibleItems !== [] || in_array($group['title'], ['Communication', 'Configuration'], true))
                <section class="admin-nav-group {{ $isActive ? 'is-active' : '' }}" data-admin-group>
                    <button class="admin-nav-group__toggle" type="button" aria-expanded="{{ $isActive ? 'true' : 'false' }}" data-admin-group-toggle title="{{ $group['title'] }}">
                        <i class="fa-solid {{ $group['icon'] }}" aria-hidden="true"></i><span>{{ $group['title'] }}</span><i class="fa-solid fa-chevron-down admin-nav-group__chevron" aria-hidden="true"></i>
                    </button>
                    <div class="admin-nav-group__items" @if (! $isActive) hidden @endif>
                        @if ($visibleItems === [])
                            <span class="admin-nav-coming-soon">Aucune page de gestion disponible pour le moment.</span>
                        @endif
                        @foreach ($visibleItems as $item)
                            @php($itemActive = request()->routeIs($item['route']) || (! ($item['exact'] ?? false) && request()->routeIs(str_replace('.index', '.*', $item['route']))))
                            <a class="dashboard-nav-link admin-nav-link {{ $itemActive ? 'is-active' : '' }}" href="{{ route($item['route']) }}" title="{{ $item['label'] }}" @if ($itemActive) aria-current="page" @endif>
                                <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </nav>
@endsection

@section('dashboard_sidebar_footer')
    <div class="dashboard-security-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Administration protégée</span></div>
    <form method="POST" action="{{ route('admin.logout') }}" class="admin-logout-form">
        @csrf
        <button type="submit" class="admin-logout-button"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Déconnexion</span></button>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.querySelector('.admin-layout [data-dashboard-sidebar]');
            const nav = sidebar?.querySelector('[data-admin-navigation]');
            const pin = nav?.querySelector('[data-admin-pin]');
            const content = document.querySelector('.admin-layout .dashboard-content');

            if (!sidebar || !nav || !pin || !content) return;

            nav.querySelectorAll('[data-admin-group-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const group = toggle.closest('[data-admin-group]');
                    const children = group?.querySelector('.admin-nav-group__items');
                    const expanded = toggle.getAttribute('aria-expanded') === 'true';
                    if (!group || !children) return;
                    toggle.setAttribute('aria-expanded', String(!expanded));
                    children.hidden = expanded;
                });
            });

            pin.addEventListener('click', () => {
                const pinned = pin.getAttribute('aria-pressed') !== 'true';
                pin.setAttribute('aria-pressed', String(pinned));
                pin.setAttribute('aria-label', pinned ? 'Désépingler la barre' : 'Épingler la barre développée');
                pin.title = pinned ? 'Désépingler la barre' : 'Épingler la barre';
                const label = pin.querySelector('span');
                if (label) label.textContent = pinned ? 'Barre épinglée' : 'Épingler la barre';
                sidebar.classList.toggle('is-pinned', pinned);
                content.classList.toggle('is-admin-sidebar-pinned', pinned);
            });
        });
    </script>
@endpush
