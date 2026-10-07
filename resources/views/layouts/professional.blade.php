@extends('layouts.dashboard')

@section('dashboard_eyebrow', 'ESPACE PROFESSIONNEL')
@section('dashboard_title', 'Mon activité')

@section('dashboard_sidebar')
    <nav aria-label="Navigation professionnel">
        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Principal</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional') }}">
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                <span>Tableau de bord</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/profile') }}">
                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                <span>Profil professionnel</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/services') }}">
                <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                <span>Services</span>
            </a>
        </section>

        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Activité</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/requests') }}">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                <span>Demandes</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/quotes') }}">
                <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
                <span>Devis</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/orders') }}">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span>Commandes</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/reviews') }}">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                <span>Avis</span>
            </a>
        </section>

        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Finances</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/revenues') }}">
                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                <span>Revenus</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/wallet') }}">
                <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                <span>Wallet</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/withdrawals') }}">
                <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
                <span>Retraits</span>
            </a>
        </section>

        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Communication</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/messages') }}">
                <i class="fa-solid fa-comments" aria-hidden="true"></i>
                <span>Messages</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professional/notifications') }}">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                <span>Notifications</span>
            </a>
        </section>
    </nav>
@endsection

@section('dashboard_sidebar_footer')
    <div class="dashboard-security-note">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <span>Espace sécurisé</span>
    </div>
@endsection
