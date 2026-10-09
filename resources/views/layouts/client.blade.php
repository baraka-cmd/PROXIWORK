@extends('layouts.dashboard')

@section('dashboard_eyebrow', 'ESPACE CLIENT')
@section('dashboard_title', 'Mon espace')

@section('dashboard_sidebar')
    <nav aria-label="Navigation client">
        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Principal</h2>

            <a class="dashboard-nav-link" data-nav-link data-nav-exact href="{{ url('/client') }}">
                <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                <span>Tableau de bord</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/search') }}">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span>Rechercher</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/professionals') }}">
                <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                <span>Professionnels</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/services') }}">
                <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                <span>Services</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/favorites') }}">
                <i class="fa-solid fa-heart" aria-hidden="true"></i>
                <span>Favoris</span>
            </a>
        </section>

        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Activité</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/requests') }}">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                <span>Demandes</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/quotes') }}">
                <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
                <span>Devis</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/orders') }}">
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span>Commandes</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/payments') }}">
                <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                <span>Paiements</span>
            </a>
        </section>

        <section class="dashboard-nav-section">
            <h2 class="dashboard-nav-title">Compte</h2>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/profile') }}">
                <i class="fa-solid fa-user-circle" aria-hidden="true"></i>
                <span>Profil</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/addresses') }}">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span>Adresses</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/notifications') }}">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                <span>Notifications</span>
            </a>

            <a class="dashboard-nav-link" data-nav-link href="{{ url('/client/messages') }}">
                <i class="fa-solid fa-comments" aria-hidden="true"></i>
                <span>Messages</span>
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
