@extends('layouts.client')

@section('title', 'Confirmer la nouvelle adresse e-mail')

@section('content')
@push('head')
    @vite('resources/css/pages/client/profile.css')
@endpush

<div class="client-profile-page profile-email-confirm-page">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">SÉCURITÉ DU COMPTE</p>
            <h1>Confirmer la nouvelle adresse e-mail</h1>
            <p>Vérifiez l’adresse avant de valider le changement de votre compte.</p>
        </div>
    </header>

    <section class="client-panel profile-section" aria-labelledby="email-confirm-title">
        <span class="profile-section-icon" aria-hidden="true"><i class="fa-solid fa-envelope-circle-check"></i></span>
        <h2 id="email-confirm-title">Confirmez que vous avez accès à cette adresse</h2>
        <p>La nouvelle adresse demandée est :</p>
        <p class="profile-confirm-email"><strong>{{ $pendingEmail }}</strong></p>
        <p class="profile-muted-copy">En confirmant, cette adresse remplacera l’adresse de connexion actuelle et sera marquée comme vérifiée. Les autres sessions et jetons API seront révoqués. Si vous n’avez pas demandé ce changement, ne confirmez pas.</p>

        <form method="POST" action="{{ route('client.profile.email-change.confirm', ['token' => $token]) }}" class="profile-form">
            @csrf
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Confirmer cette adresse e-mail</button>
            <a class="btn btn-secondary" href="{{ route('client.profile.edit') }}">Ne pas confirmer</a>
        </form>
    </section>
</div>
@endsection
