@extends('layouts.app')

@section('body_class', 'app-shell public-layout')

@section('body')
    <div class="app-shell">
        <header class="public-header">
            <nav class="public-nav page-container" aria-label="Navigation principale">
                <a class="brand" href="{{ url('/') }}" aria-label="PROXIWORK — accueil">
                    <span class="brand-mark" aria-hidden="true">
                        <i class="fa-solid fa-link"></i>
                    </span>
                    <span>PROXIWORK</span>
                </a>

                <div class="public-nav-links">
                    <a class="public-nav-link" data-nav-link data-nav-exact href="{{ url('/') }}">
                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                        <span>Accueil</span>
                    </a>

                    @if (Route::has('public.search'))
                        <a class="public-nav-link" data-nav-link href="{{ route('public.search') }}">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <span>Rechercher</span>
                        </a>
                    @endif

                    @if (Route::has('public.professionals.index'))
                        <a class="public-nav-link" data-nav-link href="{{ route('public.professionals.index') }}">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            <span>Professionnels</span>
                        </a>
                    @endif

                    @if (Route::has('public.services.index'))
                        <a class="public-nav-link" data-nav-link href="{{ route('public.services.index') }}">
                            <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                            <span>Services</span>
                        </a>
                    @endif
                </div>

                <div class="public-nav-actions">
                    @auth
                        <a class="public-nav-link optional-desktop-action" data-nav-link href="{{ url('/dashboard') }}">
                            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                            <span>Mon espace</span>
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a class="public-nav-link optional-desktop-action" href="{{ route('login') }}">
                                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                                <span>Connexion</span>
                            </a>
                        @endif
                    @endauth

                    <button
                        class="mobile-menu-toggle"
                        type="button"
                        data-mobile-menu-toggle="#public-mobile-navigation"
                        aria-expanded="false"
                        aria-controls="public-mobile-navigation"
                        aria-label="Ouvrir le menu"
                    >
                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                    </button>
                </div>
            </nav>

            <div id="public-mobile-navigation" class="public-mobile-navigation page-container" aria-hidden="true">
                <nav aria-label="Navigation mobile">
                    <a class="public-nav-link" data-nav-link data-nav-exact href="{{ url('/') }}">
                        <i class="fa-solid fa-house" aria-hidden="true"></i>
                        <span>Accueil</span>
                    </a>

                    @if (Route::has('public.search'))
                        <a class="public-nav-link" data-nav-link href="{{ route('public.search') }}">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <span>Rechercher</span>
                        </a>
                    @endif

                    @if (Route::has('public.professionals.index'))
                        <a class="public-nav-link" data-nav-link href="{{ route('public.professionals.index') }}">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            <span>Professionnels</span>
                        </a>
                    @endif

                    @if (Route::has('public.services.index'))
                        <a class="public-nav-link" data-nav-link href="{{ route('public.services.index') }}">
                            <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                            <span>Services</span>
                        </a>
                    @endif

                    @auth
                        <a class="public-nav-link" data-nav-link href="{{ url('/dashboard') }}">
                            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                            <span>Mon espace</span>
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a class="public-nav-link" href="{{ route('login') }}">
                                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                                <span>Connexion</span>
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main-content" class="public-main">
            @yield('content')
        </main>

        <footer class="public-footer">
            <div class="page-container public-footer__inner">
                <div class="public-footer__brand">
                    <a class="brand" href="{{ url('/') }}" aria-label="PROXIWORK — accueil">
                        <span class="brand-mark" aria-hidden="true">
                            <i class="fa-solid fa-link"></i>
                        </span>
                        <span>PROXIWORK</span>
                    </a>
                    <p class="public-footer__description">
                        Trouvez un professionnel, comparez les services et gardez vos échanges au même endroit.
                    </p>
                </div>

                <nav class="public-footer__navigation" aria-label="Liens de pied de page">
                    <a href="{{ url('/') }}">Accueil</a>

                    @if (Route::has('public.search'))
                        <a href="{{ route('public.search') }}">Rechercher un service</a>
                    @endif

                    @if (Route::has('public.professionals.index'))
                        <a href="{{ route('public.professionals.index') }}">Professionnels</a>
                    @endif

                    @auth
                        <a href="{{ url('/dashboard') }}">Mon espace</a>
                    @elseif (Route::has('login'))
                        <a href="{{ route('login') }}">Connexion</a>
                    @endauth
                </nav>

                <div class="public-footer__legal">
                    <small>&copy; {{ now()->year }} PROXIWORK</small>
                    <small>Tous droits réservés.</small>
                </div>
            </div>
        </footer>
    </div>
@endsection
