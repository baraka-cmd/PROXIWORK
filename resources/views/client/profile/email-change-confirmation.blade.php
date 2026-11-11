@extends('layouts.client')

@section('title', 'Confirmer la nouvelle adresse e-mail')

@section('content')
@push('head')
    @vite(['resources/css/pages/client/profile.css', 'resources/js/pages/client/profile.js'])
@endpush

<div class="client-profile-page profile-email-confirm-page">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">SÉCURITÉ DU COMPTE</p>
            <h1>Confirmer la nouvelle adresse e-mail</h1>
            <p>Vérifiez l’adresse et les conséquences avant de valider le changement de votre compte.</p>
        </div>
    </header>

    <section class="client-panel profile-section" aria-labelledby="email-confirm-title">
        <span class="profile-section-icon" aria-hidden="true"><i class="fa-solid fa-envelope-circle-check"></i></span>
        <p class="profile-section-kicker">CONFIRMATION DE SÉCURITÉ</p>
        <h2 id="email-confirm-title">Confirmez que vous contrôlez cette adresse</h2>
        <p>Vous avez demandé à utiliser cette adresse pour vous connecter à PROXIWORK :</p>
        <p class="profile-confirm-email"><strong>{{ $pendingEmail }}</strong></p>
        <div class="profile-confirm-consequences">
            <strong>Après confirmation</strong>
            <ul>
                <li>Cette adresse remplacera votre adresse de connexion actuelle et sera marquée comme vérifiée.</li>
                <li>Les autres sessions et les jetons API seront révoqués ; vous devrez peut-être vous reconnecter.</li>
            </ul>
        </div>
        <p class="profile-muted-copy">Si vous n’avez pas demandé ce changement, ne confirmez pas. Cette page nécessite une session connectée. Si votre connexion a expiré, reconnectez-vous puis ouvrez à nouveau le lien reçu par e-mail.</p>

        <form method="POST" action="{{ route('client.profile.email-change.confirm', ['token' => $token]) }}" class="profile-form" data-submit-label="Confirmation en cours…">
            @csrf
            <button class="btn btn-primary" type="submit" data-submit-label="Confirmation en cours…"><i class="fa-solid fa-check" aria-hidden="true"></i> Confirmer cette adresse e-mail</button>
            <a class="btn btn-secondary" href="{{ route('client.profile.edit') }}">Ne pas confirmer</a>
        </form>
    </section>
</div>
@endsection
