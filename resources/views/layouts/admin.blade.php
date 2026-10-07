@extends('layouts.dashboard')

@section('dashboard_eyebrow', 'CENTRE DE CONTRÔLE')
@section('dashboard_title', 'Administration')

@section('dashboard_sidebar')
    <nav aria-label="Navigation administration">
        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Pilotage</h2>

            <a class="dashboard-nav-link" data-nav-link data-nav-exact href="{{ url('/admin/dashboard') }}">
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/analytics') }}">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                <span>Analytics</span>
            </a>
        </section>

        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Plateforme</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/users') }}">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span>Utilisateurs</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/professionals') }}">
                <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                <span>Professionnels</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/categories') }}">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                <span>Catégories</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/services') }}">
                <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                <span>Services</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/orders') }}">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span>Commandes</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/payments') }}">
                <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                <span>Paiements</span>
            </a>
        </section>

        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Administration</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/roles') }}">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                <span>Rôles</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/permissions') }}">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                <span>Permissions</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/verification') }}">
                <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                <span>Vérification</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/reports') }}">
                <i class="fa-solid fa-flag" aria-hidden="true"></i>
                <span>Modération</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/support') }}">
                <i class="fa-solid fa-headset" aria-hidden="true"></i>
                <span>Support</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/admin/audit') }}">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <span>Audit</span>
            </a>

            @if (Route::has('admin.rbac.dashboard'))
                <a class="dashboard-nav-link" data-nav-link href="{{ route('admin.rbac.dashboard') }}">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span>RBAC</span>
                </a>
            @endif
        </section>
    </nav>
@endsection

@section('dashboard_sidebar_footer')
    <div class="dashboard-security-note">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <span>Administration protégée</span>
    </div>
@endsection
