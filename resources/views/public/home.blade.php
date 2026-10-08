@extends('layouts.public')

@section('title', 'Trouvez les bons professionnels — PROXIWORK')
@section('meta_description', 'Recherchez des professionnels, comparez les propositions et suivez vos projets avec PROXIWORK.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/home.css')
    @endunless
@endpush

@section('content')
    <section class="home-hero" aria-labelledby="home-hero-title">
        <div class="page-container home-hero__grid">
            <div class="home-hero__content">
                <span class="home-eyebrow">
                    <span class="home-eyebrow__dot" aria-hidden="true"></span>
                    LA PLATEFORME DE SERVICES
                </span>

                <h1 id="home-hero-title">Les bonnes compétences. <span>Les bons projets.</span> Au même endroit.</h1>
                <p class="home-hero__lead">
                    PROXIWORK facilite la rencontre entre les personnes qui ont un besoin et les professionnels qui peuvent y répondre.
                    Recherchez, échangez et suivez vos projets dans un espace organisé.
                </p>

                <form class="home-search" method="GET" action="{{ route('public.search') }}" role="search">
                    <label class="sr-only" for="home-search-input">Quel professionnel recherchez-vous ?</label>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input id="home-search-input" type="search" name="search" placeholder="Ex. développeur web, électricien…" autocomplete="off">
                    <button class="button button--primary button--lg" type="submit">Rechercher</button>
                </form>

                <div class="home-hero__actions">
                    <a class="button button--secondary button--lg" href="{{ route('public.professionals.index') }}">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                        Découvrir les professionnels
                    </a>
                    <a class="home-text-link" href="{{ route('register') }}">
                        Créer un compte
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>

                <p class="home-hero__note">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    Des parcours structurés pour les demandes, les devis et le suivi des commandes.
                </p>
            </div>

            <div class="home-hero__visual" aria-label="Aperçu du parcours PROXIWORK">
                <div class="home-orbit home-orbit--one" aria-hidden="true"></div>
                <div class="home-orbit home-orbit--two" aria-hidden="true"></div>

                <article class="home-preview-card home-preview-card--main">
                    <div class="home-preview-card__top">
                        <span class="home-preview-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <span class="home-preview-label">VOTRE BESOIN</span>
                        <span class="home-preview-status"><i class="fa-solid fa-circle" aria-hidden="true"></i> Recherche</span>
                    </div>
                    <h2>Le bon professionnel commence par une recherche claire.</h2>
                    <div class="home-preview-search">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <span>Compétence, métier ou localisation</span>
                    </div>
                    <div class="home-preview-tags" aria-label="Exemples de domaines">
                        <span>Développement</span>
                        <span>Design</span>
                        <span>Services locaux</span>
                    </div>
                </article>

                <article class="home-preview-card home-preview-card--request">
                    <span class="home-preview-mini-icon" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                    <div>
                        <strong>Demande structurée</strong>
                        <p>Un besoin, des échanges et un suivi au même endroit.</p>
                    </div>
                    <i class="fa-solid fa-check home-preview-check" aria-hidden="true"></i>
                </article>

                <article class="home-preview-card home-preview-card--quote">
                    <span class="home-preview-mini-icon home-preview-mini-icon--amber" aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    <div>
                        <strong>Proposition claire</strong>
                        <p>Consultez les devis et les étapes de votre projet.</p>
                    </div>
                </article>

                <span class="home-visual-caption"><span aria-hidden="true"></span> Un parcours plus lisible, étape par étape</span>
            </div>
        </div>
    </section>

    <section class="home-trust-strip" aria-label="Principes de PROXIWORK">
        <div class="page-container home-trust-strip__inner">
            <div><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Recherche ciblée</span></div>
            <div><i class="fa-solid fa-comments" aria-hidden="true"></i><span>Échanges organisés</span></div>
            <div><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>Suivi des demandes</span></div>
            <div><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Accès protégés</span></div>
        </div>
    </section>

    <section class="home-section home-section--audiences" aria-labelledby="home-audiences-title">
        <div class="page-container">
            <header class="home-section-heading">
                <span class="home-section-heading__eyebrow">UNE PLATEFORME, DEUX PARCOURS</span>
                <h2 id="home-audiences-title">Pensée pour celles et ceux qui <span>font avancer les projets.</span></h2>
                <p>Que vous cherchiez une compétence ou que vous proposiez vos services, PROXIWORK rassemble les outils utiles pour avancer avec plus de clarté.</p>
            </header>

            <div class="home-audience-grid">
                <article class="home-audience-card home-audience-card--client">
                    <div class="home-audience-card__icon" aria-hidden="true"><i class="fa-solid fa-compass"></i></div>
                    <span class="home-audience-card__eyebrow">CÔTÉ CLIENT</span>
                    <h3>Trouvez une réponse adaptée à votre besoin.</h3>
                    <p>Explorez les profils, formulez une demande, consultez les propositions et retrouvez vos commandes dans votre espace.</p>
                    <ul>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Recherche de professionnels et filtres utiles</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Demandes et devis regroupés</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Historique des commandes et paiements</li>
                    </ul>
                    <a class="home-card-link" href="{{ route('public.search') }}">Rechercher un professionnel <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>

                <article class="home-audience-card home-audience-card--professional">
                    <div class="home-audience-card__icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></div>
                    <span class="home-audience-card__eyebrow">CÔTÉ PROFESSIONNEL</span>
                    <h3>Présentez votre activité et suivez vos opportunités.</h3>
                    <p>Organisez vos services, gérez les demandes reçues et gardez une vue claire sur les commandes et votre activité.</p>
                    <ul>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Profil et catalogue de services</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Suivi des demandes et des commandes</li>
                        <li><i class="fa-solid fa-check" aria-hidden="true"></i> Espace dédié aux revenus et retraits</li>
                    </ul>
                    <a class="home-card-link" href="{{ route('register') }}">Rejoindre PROXIWORK <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section home-section--steps" aria-labelledby="home-steps-title">
        <div class="page-container home-steps-layout">
            <header class="home-section-heading home-section-heading--left">
                <span class="home-section-heading__eyebrow">COMMENT ÇA MARCHE</span>
                <h2 id="home-steps-title">Avancez avec une <span>méthode claire.</span></h2>
                <p>Les principales étapes sont regroupées pour vous aider à garder le fil, depuis le premier besoin jusqu'au suivi du projet.</p>
                <a class="button button--primary" href="{{ route('public.professionals.index') }}">Explorer les profils <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>

            <ol class="home-steps-list">
                <li>
                    <span class="home-step-number">01</span>
                    <div><h3>Décrivez votre besoin</h3><p>Recherchez un métier ou une compétence, puis précisez les critères qui comptent pour votre projet.</p></div>
                </li>
                <li>
                    <span class="home-step-number">02</span>
                    <div><h3>Échangez et comparez</h3><p>Centralisez les demandes, les messages et les propositions pour prendre une décision éclairée.</p></div>
                </li>
                <li>
                    <span class="home-step-number">03</span>
                    <div><h3>Suivez les étapes</h3><p>Retrouvez les commandes, leur historique et les informations de paiement depuis votre espace.</p></div>
                </li>
            </ol>
        </div>
    </section>

    <section class="home-final-cta" aria-labelledby="home-final-cta-title">
        <div class="page-container home-final-cta__inner">
            <div>
                <span class="home-section-heading__eyebrow">PRÊT À COMMENCER ?</span>
                <h2 id="home-final-cta-title">Faites le premier pas avec PROXIWORK.</h2>
                <p>Découvrez les professionnels ou créez votre compte pour retrouver vos activités dans un espace dédié.</p>
            </div>
            <div class="home-final-cta__actions">
                <a class="button button--accent button--lg" href="{{ route('public.professionals.index') }}">Découvrir les professionnels <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                <a class="button button--light button--lg" href="{{ route('register') }}">Créer un compte</a>
            </div>
        </div>
    </section>
@endsection
