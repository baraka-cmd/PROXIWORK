@extends('layouts.public')

@section('title', 'PROXIWORK — Services et professionnels de confiance')
@section('meta_description', 'Trouvez des professionnels vérifiés et des services publiés sur PROXIWORK.')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/public/home.css', 'resources/css/pages/public/search.css'])
    @endunless
@endpush

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/public/home.js')
    @endunless
@endpush

@section('content')
    <section class="home-hero home-hero--dynamic" aria-labelledby="home-hero-title" data-home-carousel>
        <div class="page-container">
            @if ($slides->isNotEmpty())
                @foreach ($slides as $index => $slide)
                    <article class="home-slide {{ $index === 0 ? 'is-active' : '' }}" data-home-slide aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                        <div class="home-hero__grid">
                            <div class="home-hero__content">
                                <span class="home-eyebrow"><span class="home-eyebrow__dot" aria-hidden="true"></span>PROXIWORK</span>
                                <h1 id="home-hero-title">{{ $slide->title }}</h1>
                                <p class="home-hero__lead">{{ $slide->subtitle }}</p>
                                <div class="home-hero__actions"><a class="button button--primary button--lg" href="{{ url($slide->primary_route) }}">{{ $slide->primary_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@if($slide->secondary_route && $slide->secondary_label)<a class="home-text-link" href="{{ url($slide->secondary_route) }}">{{ $slide->secondary_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif</div>
                            </div>
                            <div class="home-hero__image"><picture>@if($slide->mobile_image_path)<source media="(max-width: 760px)" srcset="{{ asset('storage/'.$slide->mobile_image_path) }}">@endif @if($slide->image_path)<img src="{{ asset('storage/'.$slide->image_path) }}" alt="{{ $slide->image_alt ?: $slide->title }}">@else<div class="home-hero__image-placeholder"><i class="fa-solid fa-compass" aria-hidden="true"></i></div>@endif</picture></div>
                        </div>
                    </article>
                @endforeach
                <div class="home-carousel-controls" aria-label="Contrôle du carrousel"><button type="button" data-home-prev aria-label="Diapositive précédente"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button><div class="home-carousel-dots">@foreach($slides as $index => $slide)<button type="button" data-home-dot="{{ $index }}" aria-label="Afficher la diapositive {{ $index + 1 }}"></button>@endforeach</div><button type="button" data-home-next aria-label="Diapositive suivante"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button><button type="button" data-home-pause aria-label="Mettre en pause le carrousel"><i class="fa-solid fa-pause" aria-hidden="true"></i></button></div>
            @elseif ($heroServices->isNotEmpty())
                @foreach ($heroServices as $index => $service)
                    @php($cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first())
                    <article class="home-slide {{ $index === 0 ? 'is-active' : '' }}" data-home-slide aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
                        <div class="home-hero__grid"><div class="home-hero__content"><span class="home-eyebrow"><span class="home-eyebrow__dot" aria-hidden="true"></span>SERVICE PUBLIÉ</span><h1 id="home-hero-title">{{ $service->title }}</h1><p class="home-hero__lead">{{ $service->short_description ?: Str::limit($service->description, 180) }}</p><div class="home-hero__actions"><a class="button button--primary button--lg" href="{{ route('public.services.show', $service) }}">Découvrir le service <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><a class="home-text-link" href="{{ route('public.search') }}">Explorer le catalogue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div></div><div class="home-hero__image">@if($cover)<img src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}">@else<div class="home-hero__image-placeholder"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></div>@endif</div></div>
                    </article>
                @endforeach
                <div class="home-carousel-controls" aria-label="Contrôle du carrousel"><button type="button" data-home-prev aria-label="Diapositive précédente"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button><div class="home-carousel-dots">@foreach($heroServices as $index => $service)<button type="button" data-home-dot="{{ $index }}" aria-label="Afficher la diapositive {{ $index + 1 }}"></button>@endforeach</div><button type="button" data-home-next aria-label="Diapositive suivante"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button><button type="button" data-home-pause aria-label="Mettre en pause le carrousel"><i class="fa-solid fa-pause" aria-hidden="true"></i></button></div>
            @else
                <div class="home-hero__grid"><div class="home-hero__content"><span class="home-eyebrow"><span class="home-eyebrow__dot" aria-hidden="true"></span>PROXIWORK</span><h1 id="home-hero-title">Trouvez le bon professionnel pour votre projet.</h1><p class="home-hero__lead">Découvrez les services et professionnels disponibles sur PROXIWORK.</p><form class="home-search" method="GET" action="{{ route('public.search') }}"><label class="sr-only" for="home-search-input">Quel service recherchez-vous ?</label><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="home-search-input" type="search" name="search" placeholder="Quel service recherchez-vous ?"><button class="button button--primary" type="submit">Rechercher</button></form></div><div class="home-hero__image home-hero__image-placeholder"><i class="fa-solid fa-compass" aria-hidden="true"></i></div></div>
            @endif
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="home-section home-section--dynamic" aria-labelledby="home-categories-title"><div class="page-container"><header class="home-section-heading"><span class="home-section-heading__eyebrow">EXPLORER PAR DOMAINE</span><h2 id="home-categories-title">Des services pour vos projets.</h2><p>Parcourez les catégories actuellement représentées par des professionnels vérifiés.</p></header><div class="home-data-grid home-data-grid--categories">@foreach($categories as $category)<a class="home-data-card" href="{{ route('public.search', ['category' => $category->slug]) }}"><span class="home-data-card__icon"><i class="fa-solid {{ $category->icon ?: 'fa-layer-group' }}" aria-hidden="true"></i></span><strong>{{ $category->name }}</strong><span>{{ $category->services_count }} service{{ $category->services_count > 1 ? 's' : '' }}</span></a>@endforeach</div></div></section>
    @endif

    @if ($skills->isNotEmpty())
        <section class="home-section home-section--skills" aria-labelledby="home-skills-title"><div class="page-container"><header class="home-section-heading home-section-heading--left"><span class="home-section-heading__eyebrow">COMPÉTENCES RECHERCHÉES</span><h2 id="home-skills-title">Affinez votre recherche.</h2></header><div class="home-skill-list">@foreach($skills as $skill)<a class="badge badge--neutral" href="{{ route('public.search', ['skills' => [$skill->slug]]) }}">{{ $skill->name }} <small>{{ $skill->professional_profiles_count }}</small></a>@endforeach</div></div></section>
    @endif

    @if ($professionals->isNotEmpty())
        <section class="home-section" aria-labelledby="home-professionals-title"><div class="page-container"><header class="home-section-heading"><span class="home-section-heading__eyebrow">PROFESSIONNELS ADMISSIBLES</span><h2 id="home-professionals-title">Des profils que vous pouvez découvrir.</h2><p>Les profils présentés respectent les règles de visibilité de PROXIWORK.</p></header><div class="professional-grid">@foreach($professionals as $professional)@include('public.partials.professional-card', ['professional' => $professional])@endforeach</div><div class="home-section__more"><a class="button button--secondary" href="{{ route('public.professionals.index') }}">Voir tout l’annuaire <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div></div></section>
    @endif

    @if ($services->isNotEmpty())
        <section class="home-section home-section--surface" aria-labelledby="home-services-title"><div class="page-container"><header class="home-section-heading"><span class="home-section-heading__eyebrow">SERVICES À DÉCOUVRIR</span><h2 id="home-services-title">Des prestations publiées et comparables.</h2></header><div class="public-service-grid">@foreach($services->take(4) as $service)@php($cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first())<article class="public-service-card surface-card"><a class="public-service-card__media" href="{{ route('public.services.show', $service) }}">@if($cover)<img src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}" loading="lazy">@else<span><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>@endif</a><div class="public-service-card__body"><div class="public-service-card__category">{{ $service->category->name }}</div><h3><a href="{{ route('public.services.show', $service) }}">{{ $service->title }}</a></h3><p>{{ Str::limit($service->short_description ?: $service->description, 130) }}</p><div class="public-service-card__footer"><strong>{{ $service->pricing_type->value === 'quote' ? 'Sur devis' : number_format((float)($service->price ?? $service->price_min), 2, ',', ' ').' '.$service->currency }}</strong><a class="button button--primary button--sm" href="{{ route('public.services.show', $service) }}">Voir</a></div></div></article>@endforeach</div><div class="home-section__more"><a class="button button--secondary" href="{{ route('public.search') }}">Découvrir tous les services <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div></div></section>
    @endif

    <section class="home-section home-section--steps" aria-labelledby="home-steps-title"><div class="page-container home-steps-layout"><header class="home-section-heading home-section-heading--left"><span class="home-section-heading__eyebrow">COMMENT ÇA MARCHE</span><h2 id="home-steps-title">Avancez avec une méthode claire.</h2><p>PROXIWORK organise la découverte, les échanges et le suivi des projets dans un même parcours.</p></header><ol class="home-steps-list"><li><span class="home-step-number">01</span><div><h3>Recherchez</h3><p>Explorez les services et professionnels réellement disponibles.</p></div></li><li><span class="home-step-number">02</span><div><h3>Comparez</h3><p>Consultez les informations publiées avant de contacter un professionnel.</p></div></li><li><span class="home-step-number">03</span><div><h3>Suivez</h3><p>Retrouvez vos demandes et échanges dans votre espace sécurisé.</p></div></li></ol></div></section>

    @if ($reviews->isNotEmpty())
        <section class="home-section" aria-labelledby="home-reviews-title"><div class="page-container"><header class="home-section-heading"><span class="home-section-heading__eyebrow">AVIS PUBLIÉS</span><h2 id="home-reviews-title">Des retours issus de la plateforme.</h2></header><div class="home-review-grid">@foreach($reviews as $review)<article class="home-review-card surface-card"><div class="home-review-card__rating">{{ $review->rating }}/5 <span aria-label="étoiles">★★★★★</span></div><p>« {{ $review->comment }} »</p><small>{{ $review->client?->name ?: 'Client PROXIWORK' }}</small></article>@endforeach</div></div></section>
    @endif

    <section class="home-final-cta" aria-labelledby="home-final-cta-title"><div class="page-container home-final-cta__inner"><div><span class="home-section-heading__eyebrow">PROXIWORK</span><h2 id="home-final-cta-title">Trouvez votre prochaine étape.</h2><p>Explorez les services ou présentez votre activité professionnelle.</p></div><div class="home-final-cta__actions"><a class="button button--accent button--lg" href="{{ route('public.search') }}">Explorer les services <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><a class="button button--light button--lg" href="{{ route('register') }}">Créer un compte</a></div></div></section>
@endsection