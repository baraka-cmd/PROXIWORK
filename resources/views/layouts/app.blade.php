<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">

    <title>@yield('title', config('app.name', 'PROXIWORK'))</title>
    <meta name="description" content="@yield('meta_description', 'PROXIWORK — trouvez des professionnels et développez votre activité.')">

    <script>
        (() => {
            try {
                const savedTheme = window.localStorage.getItem('proxiwork-theme');
                const theme = savedTheme === 'dark' ? 'dark' : 'light';

                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            } catch {
                document.documentElement.dataset.theme = 'light';
                document.documentElement.style.colorScheme = 'light';
            }
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
