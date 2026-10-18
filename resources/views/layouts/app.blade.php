<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'PROXIWORK'))</title>
    <meta name="description" content="@yield('meta_description', 'PROXIWORK — trouvez des professionnels et développez votre activité.')">

    <script>
        (function () {
            var root = document.documentElement;
            var preference = null;

            try {
                preference = window.localStorage.getItem('proxiwork-theme');
            } catch (error) {
                // Storage may be disabled; system preference remains available.
            }

            var validPreference = preference === 'light' || preference === 'dark';
            var systemPrefersDark = window.matchMedia
                && window.matchMedia('(prefers-color-scheme: dark)').matches;

            root.dataset.theme = validPreference ? preference : (systemPrefersDark ? 'dark' : 'light');
            root.dataset.themePreference = validPreference ? 'user' : 'system';
        })();
    </script>

    <link
        rel="stylesheet"
        href="{{ asset('assets/fontawesome/css/all.min.css') }}"
    >
    
    @fonts
    @unless (app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endunless
    @stack('head')
</head>
<body class="@yield('body_class', 'app-shell')">
    <a class="skip-link" href="#main-content">Aller au contenu principal</a>

    @if (session('success'))
        <div class="page-container" aria-label="Message de réussite">
            <x-success :message="session('success')" />
        </div>
    @endif

    @yield('body')

    <x-confirmation />

    @stack('scripts')
</body>
</html>
