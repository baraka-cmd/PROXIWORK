<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PROXIWORK — RBAC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-rbac.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span><i class="fa-solid fa-bolt"></i></span>
            <strong>PROXIWORK</strong>
        </div>
        <nav aria-label="Navigation administration">
            <a class="nav-item active" href="{{ route('admin.rbac.dashboard') }}">
                <i class="fa-solid fa-shield-halved"></i><span>RBAC</span>
            </a>
        </nav>
        <div class="sidebar-bottom">
            <div class="security-note"><i class="fa-solid fa-circle-check"></i><span>Session protégée</span></div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="logout-button" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <div>
                <span class="eyebrow">CENTRE DE CONTRÔLE</span>
                <h1>Rôles & permissions</h1>
                <p>Vue d’ensemble de la matrice d’autorisation de PROXIWORK.</p>
            </div>
            <div class="admin-user">
                <span class="avatar"><i class="fa-solid fa-user-shield"></i></span>
                <div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
            </div>
        </header>

        <section class="metric-grid" aria-label="Indicateurs RBAC">
            <article class="metric-card"><span class="metric-icon"><i class="fa-solid fa-user-lock"></i></span><div><small>Rôles</small><strong>{{ $metrics['roles'] }}</strong></div></article>
            <article class="metric-card"><span class="metric-icon"><i class="fa-solid fa-key"></i></span><div><small>Permissions</small><strong>{{ $metrics['permissions'] }}</strong></div></article>
            <article class="metric-card"><span class="metric-icon"><i class="fa-solid fa-users-gear"></i></span><div><small>Utilisateurs avec rôle</small><strong>{{ $metrics['usersWithRoles'] }}</strong></div></article>
            <article class="metric-card"><span class="metric-icon"><i class="fa-solid fa-diagram-project"></i></span><div><small>Affectations</small><strong>{{ $metrics['assignments'] }}</strong></div></article>
        </section>

        <section class="panel-grid">
            <article class="panel chart-panel">
                <div class="panel-heading">
                    <div><span class="eyebrow">RÉPARTITION</span><h2>Utilisateurs par rôle</h2></div>
                    <span class="panel-icon"><i class="fa-solid fa-chart-pie"></i></span>
                </div>
                <div class="chart-wrap"><canvas id="roleDistributionChart" aria-label="Répartition des utilisateurs par rôle"></canvas></div>
            </article>

            <article class="panel role-panel">
                <div class="panel-heading">
                    <div><span class="eyebrow">MATRICE</span><h2>Rôles système</h2></div>
                </div>
                <div class="role-list">
                    @foreach($roleDistribution as $role)
                        <div class="role-row">
                            <span class="role-avatar"><i class="fa-solid fa-user-tag"></i></span>
                            <div><strong>{{ $role->display_name }}</strong><small>{{ $role->name }}</small></div>
                            <span class="role-count">{{ $role->users_count }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="security-banner">
            <span><i class="fa-solid fa-shield-check"></i></span>
            <div><strong>Autorisation centralisée</strong><p>Les accès sont contrôlés par rôles, permissions, middleware et policies. Les rôles système ne peuvent pas être supprimés par l’interface.</p></div>
        </section>
    </main>
</div>
<script>
window.PROXIWORK_RBAC = {
    labels: @json($roleDistribution->pluck('display_name')),
    values: @json($roleDistribution->pluck('users_count')),
};
</script>
<script src="{{ asset('js/admin-rbac.js') }}" defer></script>
</body>
</html>
