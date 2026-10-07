@extends('layouts.public')

@section('title', 'Rechercher un professionnel — PROXIWORK')
@section('meta_description', 'Recherchez un professionnel sur PROXIWORK par métier, compétence, localisation, budget, disponibilité et vérification.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/search.css')
    @endunless
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
                    <span class="eyebrow">RECHERCHE PROXIWORK</span>
                    <h1>Trouvez le bon professionnel pour votre projet.</h1>
                    <p>Décrivez votre besoin, puis affinez les résultats selon la compétence, la localisation, le budget et la disponibilité.</p>
                </div>

                <form class="directory-hero__search" method="GET" action="{{ route('public.search') }}">
                    <label class="sr-only" for="search-hero-input">Que recherchez-vous ?</label>
                    <div class="directory-hero__search-field">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input id="search-hero-input" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ex. développeur Laravel, graphiste, comptable…" autocomplete="off">
                    </div>
                    <button class="button button--primary button--lg" type="submit">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Rechercher
                    </button>
                </form>
            </header>

            <div class="directory-layout">
                <aside class="directory-sidebar" aria-label="Filtres de recherche">
                    <button class="button button--ghost directory-mobile-filter-toggle" type="button" data-filters-open aria-controls="search-filters">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        Filtres
                    </button>
                    <div id="search-filters" class="directory-filter-panel">
                        @include('public.partials.professional-filters', ['action' => route('public.search'), 'idPrefix' => 'search-filter'])
                    </div>
                </aside>

                <section class="directory-results" aria-labelledby="search-results-title">
                    <div class="directory-results__header">
                        <div>
                            <span class="eyebrow">RÉSULTATS</span>
                            <h2 id="search-results-title">
                                @if ($professionals)
                                    {{ $professionals->total() }} professionnel{{ $professionals->total() > 1 ? 's' : '' }} trouvé{{ $professionals->total() > 1 ? 's' : '' }}
                                @else
                                    Commencez votre recherche
                                @endif
                            </h2>
                        </div>
                    </div>

                    @if (!$professionals)
                        <div class="state-empty surface-card">
                            <div>
                                <div class="state-empty__icon"><i class="fa-solid fa-compass" aria-hidden="true"></i></div>
                                <h2>Quel professionnel recherchez-vous ?</h2>
                                <p>Utilisez la barre de recherche ou les filtres pour trouver des profils correspondant réellement à votre besoin.</p>
                            </div>
                        </div>
                    @elseif ($professionals->isEmpty())
                        <div class="state-empty surface-card">
                            <div>
                                <div class="state-empty__icon"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></div>
                                <h2>Aucun professionnel trouvé</h2>
                                <p>Essayez un autre métier, une autre compétence, une localisation plus large ou retirez quelques filtres.</p>
                                <div class="state-empty__actions">
                                    <a class="button button--ghost" href="{{ route('public.search') }}">
                                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                        Réinitialiser la recherche
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="professional-grid">
                            @foreach ($professionals as $professional)
                                @include('public.partials.professional-card', ['professional' => $professional])
                            @endforeach
                        </div>

                        <div class="directory-pagination">
                            {{ $professionals->onEachSide(1)->links() }}
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </section>
@endsection
