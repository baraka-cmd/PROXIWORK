@extends('layouts.app')

@section('body_class', 'app-shell dashboard-layout')

@section('body')
    <div class="dashboard-shell">
        <aside id="dashboard-sidebar" class="dashboard-sidebar" data-dashboard-sidebar aria-label="Navigation de l'espace" aria-hidden="false">
            <div class="dashboard-sidebar__header">
                <a class="brand" href="{{ url('/') }}" aria-label="PROXIWORK — accueil">
                    <span class="brand-mark" aria-hidden="true">
                        <i class="fa-solid fa-link"></i>
                    </span>
                    <span>PROXIWORK</span>
                </a>

                <button
                    class="icon-button"
                    type="button"
                    data-sidebar-close
                    aria-label="Fermer la navigation"
                >
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="dashboard-sidebar__nav">
                @yield('dashboard_sidebar')
            </div>

            @auth
                <div class="dashboard-sidebar__footer">
                    @yield('dashboard_sidebar_footer')
                </div>
            @endauth
        </aside>

        <div class="sidebar-overlay" data-sidebar-overlay></div>

        <div class="dashboard-content">
            <header class="dashboard-topbar">
                <div class="dashboard-topbar__left">
                    <button
                        class="mobile-menu-toggle"
                        type="button"
                        data-mobile-menu-toggle="#dashboard-sidebar"
                        aria-expanded="false"
                        aria-controls="dashboard-sidebar"
                        aria-label="Ouvrir la navigation"
                    >
                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                    </button>

                    <div>
                        <span class="eyebrow">@yield('dashboard_eyebrow', 'ESPACE PROXIWORK')</span>
                        <h1 class="dashboard-topbar__title">@yield('dashboard_title', 'Tableau de bord')</h1>
                    </div>
                </div>

                <div class="dashboard-topbar__actions">
                    @yield('dashboard_topbar_actions')

                    @auth
                        @php
                            $currentUser = auth()->user();
                            $availableWorkspaceLabels = [
                                'client' => 'Espace client',
                                'professional' => 'Espace professionnel',
                                'admin' => 'Administration',
                            ];
                        @endphp
                        <div class="dashboard-workspace-switcher" aria-label="Changer d’espace">
                            @foreach ($availableWorkspaceLabels as $workspaceKey => $workspaceLabel)
                                @php
                                    $workspaceAllowed = match ($workspaceKey) {
                                        'client' => $currentUser->hasRole('client'),
                                        'professional' => $currentUser->hasRole('professional'),
                                        'admin' => $currentUser->hasPermissionTo('admin.dashboard.view') || $currentUser->hasPermissionTo('rbac.view'),
                                        default => false,
                                    };
                                @endphp
                                @if ($workspaceAllowed && session('active_workspace') !== $workspaceKey)
                                    <form method="POST" action="{{ route('workspace.switch') }}">
                                        @csrf
                                        <input type="hidden" name="workspace" value="{{ $workspaceKey }}">
                                        <button type="submit">{{ $workspaceLabel }}</button>
                                    </form>
                                @endif
                            @endforeach
                        </div>

                        <div class="dashboard-user">
                            <span class="avatar" aria-hidden="true">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <div class="dashboard-user__identity">
                                <strong>{{ auth()->user()->name }}</strong>
                                <small>{{ auth()->user()->email }}</small>
                            </div>
                            <div class="dashboard-user__security">
                                <a href="{{ route('account.email.edit') }}">E-mail</a>
                                <a href="{{ route('account.password.edit') }}">Mot de passe</a>
                            </div>
                        </div>
                    @endauth
                </div>
            </header>

            <main id="main-content" class="dashboard-main">
                <header class="dashboard-page-header">
                    <div>
                        @hasSection('page_eyebrow')
                            <span class="eyebrow">@yield('page_eyebrow')</span>
                        @endif

                        <h2>@yield('page_title', 'Tableau de bord')</h2>

                        @hasSection('page_description')
                            <p>@yield('page_description')</p>
                        @endif
                    </div>

                    <div class="dashboard-page-header__actions">
                        @yield('page_actions')
                    </div>
                </header>

                @yield('content')
            </main>
        </div>
    </div>
@endsection
