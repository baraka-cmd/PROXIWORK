<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'PROXIWORK'))</title>
    <meta name="description" content="@yield('meta_description', 'PROXIWORK — trouvez des professionnels et développez votre activité.')">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

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

    @if (auth()->check() && ! auth()->user()->hasVerifiedEmail() && ! request()->routeIs('verification.notice', 'web.verification.verify'))
        <aside class="email-verification-banner" role="status" aria-label="Vérification de l’adresse e-mail">
            <div class="email-verification-banner__copy">
                <strong>Confirmez votre adresse e-mail</strong>
                <span>Votre compte reste accessible, mais certaines actions nécessiteront cette vérification.</span>
            </div>
            <a href="{{ route('verification.notice') }}">Vérifier maintenant</a>
            <a href="{{ route('account.email.edit') }}">Modifier l’adresse</a>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit">Renvoyer le lien</button>
            </form>
        </aside>
    @endif

    @yield('body')

    <x-confirmation />

    @stack('scripts')
</body>
</html>
