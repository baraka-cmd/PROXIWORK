@extends('layouts.public')

@php
    $searchTitleTerm = trim((string) ($filters['search'] ?? ''));
    $selectedCategory = $categories->firstWhere('slug', $filters['category'] ?? null);
    $searchPageTitle = $searchTitleTerm !== ''
        ? 'Résultats pour « '.e(\Illuminate\Support\Str::limit($searchTitleTerm, 60)).' » — PROXIWORK'
        : ($selectedCategory
            ? e($selectedCategory->name).' — Services PROXIWORK'
            : 'Découvrir des services — PROXIWORK');
    $searchMetaDescription = $searchTitleTerm !== ''
        ? 'Résultats de recherche PROXIWORK pour '.$searchTitleTerm.'. Découvrez les services publiés accessibles au public.'
        : 'Découvrez et comparez les services publiés sur PROXIWORK par catégorie, compétence, localisation, tarif et disponibilité.';
@endphp

@section('title', $searchPageTitle)
@section('meta_description', e($searchMetaDescription))

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/search.css')
    @endunless
    @if (request()->query())
        <meta name="robots" content="noindex,follow">
    @endif
@endpush

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/public/search.js')
    @endunless
@endpush

@section('content')
    <section class="directory-page directory-page--search" data-directory-page>
        <div class="page-container">
            <header class="directory-hero">
                <div class="directory-hero__content">
                    <span class="eyebrow">DÉCOUVERTE PROXIWORK</span>
                    <h1>Trouvez le service adapté à votre besoin.</h1>
                    <p>Explorez les prestations réellement publiées, comparez les tarifs dans une même devise et consultez les informations utiles avant de contacter un professionnel.</p>
                </div>

                <form class="directory-hero__search" method="GET" action="{{ route('public.search') }}">
                    <label class="sr-only" for="search-hero-input">Que recherchez-vous ?</label>
                    <div class="directory-hero__search-field">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input id="search-hero-input" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ex. réparation téléphone, plomberie, développeur web…" autocomplete="off">
                    </div>
                    @foreach (collect($filters)->except(['search', 'page'])->all() as $filterName => $filterValue)
                        @if (is_array($filterValue))
                            @foreach ($filterValue as $item)
                                <input type="hidden" name="{{ $filterName }}[]" value="{{ $item }}">
                            @endforeach
                        @elseif ($filterValue !== null && $filterValue !== '')
                            <input type="hidden" name="{{ $filterName }}" value="{{ is_bool($filterValue) ? (int) $filterValue : $filterValue }}">
                        @endif
                    @endforeach
                    <button class="button button--primary button--lg" type="submit">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Rechercher
                    </button>
                </form>
            </header>

            @if ($errors->any())
                <div class="search-validation-alert" role="alert">
                    <strong>Certains critères de recherche doivent être corrigés.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($featuredCategories->isNotEmpty())
                <nav class="service-category-shortcuts" aria-label="Catégories à découvrir">
                    <span class="eyebrow">CATÉGORIES À DÉCOUVRIR</span>
                    <div class="service-category-shortcuts__list">
                        @foreach ($featuredCategories as $category)
                            <a class="service-category-chip" href="{{ route('public.search', ['category' => $category->slug]) }}">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif

            <div class="directory-layout">
                <aside class="directory-sidebar" aria-label="Filtres de recherche des services">
                    <button class="button button--ghost directory-mobile-filter-toggle" type="button" data-filters-open aria-controls="search-filters" aria-expanded="false">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i> Filtres
                    </button>
                    <div id="search-filters" class="directory-filter-panel">
                        @include('public.partials.service-filters')
                    </div>
                </aside>

                <section class="directory-results" aria-labelledby="search-results-title">
                    <div class="directory-results__header">
                        <div>
                            <span class="eyebrow">CATALOGUE PUBLIC</span>
                            <h2 id="search-results-title">
                                {{ $services->total() }} service{{ $services->total() === 1 ? '' : 's' }} trouvé{{ $services->total() === 1 ? '' : 's' }}
                            </h2>
                            <p class="directory-results__summary">
                                @if ($activeFiltersCount > 0)
                                    {{ $activeFiltersCount }} filtre{{ $activeFiltersCount > 1 ? 's' : '' }} actif{{ $activeFiltersCount > 1 ? 's' : '' }}.
                                    <a href="{{ route('public.search') }}">Effacer les filtres</a>
                                @else
                                    Services publiés et accessibles au public, sans connexion obligatoire.
                                @endif
                            </p>
                        </div>
                        <span class="directory-results__page">Page {{ $services->currentPage() }} / {{ $services->lastPage() }}</span>
                    </div>

                    @if ($services->isEmpty())
                        <div class="state-empty surface-card">
                            <div>
                                <div class="state-empty__icon"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></div>
                                <h2>Aucun service ne correspond à ces critères</h2>
                                <p>Essayez un terme plus général, une autre catégorie ou une zone plus large. Les tarifs ne sont comparés qu'entre services utilisant la même devise.</p>
                                <div class="state-empty__actions">
                                    <a class="button button--ghost" href="{{ route('public.search') }}">
                                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Réinitialiser la recherche
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="service-grid">
                            @foreach ($services as $service)
                                <article class="service-card surface-card">
                                    <h3><a href="{{ route('public.services.show', $service->slug) }}">{{ $service->title }}</a></h3>
                                </article>
                            @endforeach
                        </div>

                        <div class="directory-pagination" aria-label="Pagination des services">
                            {{ $services->onEachSide(1)->links() }}
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </section>
@endsection
