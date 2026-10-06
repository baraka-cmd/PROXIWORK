<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PROXIWORK — Administration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-auth.css') }}">
</head>
<body>
<main class="auth-shell">
    <section class="auth-card" aria-labelledby="login-title">
        <div class="brand">
            <span class="brand-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <div>
                <strong>PROXIWORK</strong>
                <small>Administration sécurisée</small>
            </div>
        </div>

        <div class="intro">
            <span class="eyebrow">ESPACE ADMIN</span>
            <h1 id="login-title">Bienvenue dans le centre de contrôle</h1>
            <p>Gérez les rôles et permissions de la plateforme depuis une interface protégée.</p>
        </div>

        <form method="POST" action="{{ route('admin.login.store') }}" class="login-form">
            @csrf

            <label for="email">Adresse e-mail</label>
            <div class="input-wrap">
                <i class="fa-regular fa-envelope"></i>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </div>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror

            <label for="password">Mot de passe</label>
            <div class="input-wrap">
                <i class="fa-solid fa-lock"></i>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle aria-label="Afficher le mot de passe">
                    <i class="fa-regular fa-eye"></i>
                </button>
            </div>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror

            <label class="remember">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <span>Rester connecté</span>
            </label>

            <button class="submit-button" type="submit">
                <span>Accéder à l’administration</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <footer>
            <i class="fa-solid fa-lock"></i>
            Accès réservé aux comptes autorisés
        </footer>
    </section>
</main>
<script src="{{ asset('js/admin-auth.js') }}" defer></script>
</body>
</html>
