@extends('layouts.client')

@section('title', 'Mes services — PROXIWORK')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/client/services.css')
    @endunless
@endpush

@section('content')
    <div class="client-services-page">
        <header class="client-services-heading">
            <div class="client-services-heading__copy">
                <p class="eyebrow">ESPACE CLIENT</p>
                <h1>Mes services</h1>
                <p class="page-lead">
                    Retrouvez vos demandes et le suivi de vos prestations. Pour découvrir une nouvelle offre,
                    accédez à la recherche de services.
                </p>
            </div>
            <span class="client-services-heading__icon" aria-hidden="true">
                <i class="fa-solid fa-briefcase"></i>
            </span>
        </header>

        <section class="client-services-overview" aria-label="Résumé de votre activité">
            <a class="client-services-stat surface-card" href="{{ route('client.requests.index') }}">
                <span class="client-services-stat__icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                <span class="client-services-stat__label">Demandes</span>
                <strong>{{ $requestCount }}</strong>
                <span class="client-services-stat__link">Voir mes demandes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>

            <a class="client-services-stat surface-card" href="{{ route('client.orders.index', ['bucket' => 'active']) }}">
                <span class="client-services-stat__icon" aria-hidden="true"><i class="fa-solid fa-rotate"></i></span>
                <span class="client-services-stat__label">Commandes en cours</span>
                <strong>{{ $activeOrderCount }}</strong>
                <span class="client-services-stat__link">Suivre mes commandes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>

            <a class="client-services-stat surface-card" href="{{ route('client.orders.index', ['bucket' => 'completed']) }}">
                <span class="client-services-stat__icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                <span class="client-services-stat__label">Commandes terminées</span>
                <strong>{{ $completedOrderCount }}</strong>
                <span class="client-services-stat__link">Voir mon historique <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>
        </section>

        <section class="client-services-next" aria-labelledby="client-services-next-title">
            <div class="client-services-next__heading">
                <p class="eyebrow">ACCÈS RAPIDES</p>
                <h2 id="client-services-next-title">Poursuivre votre parcours</h2>
                <p>Chaque espace conserve son rôle : demandes, devis et commandes restent suivis séparément.</p>
            </div>

            <div class="client-services-links">
                <a class="client-services-link surface-card" href="{{ route('client.search') }}">
                    <span class="client-services-link__icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <span><strong>Découvrir des services</strong><small>Rechercher une prestation selon votre besoin</small></span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a class="client-services-link surface-card" href="{{ route('client.quotes.index') }}">
                    <span class="client-services-link__icon" aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    <span><strong>Mes devis</strong><small>Examiner les propositions des professionnels</small></span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
                <a class="client-services-link surface-card" href="{{ route('client.requests.index') }}">
                    <span class="client-services-link__icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                    <span><strong>Gérer mes demandes</strong><small>Consulter leur état et les détails du besoin</small></span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </section>
    </div>
@endsection
