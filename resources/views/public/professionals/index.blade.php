@extends('layouts.public')

@section('title', 'Professionnels — PROXIWORK')
@section('meta_description', 'Découvrez les professionnels présents sur PROXIWORK et filtrez les profils selon vos besoins.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/professionals.css')
    @endunless
@endpush

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/public/professionals.js')
    @endunless
@endpush

@section('content')
    <section class="directory-page" data-directory-page>
        <div class="page-container">
            <header class="directory-section-header">
                <div>
                    <span class="eyebrow">RÉSEAU PROXIWORK</span>
                    <h1>Professionnels</h1>
                    <p>Explorez des professionnels proposant des services publiés et visibles sur la plateforme.</p>
                </div>

                <a class="button button--primary" href="{{ route('public.search') }}">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    Recherche avancée
                </a>
            </header>

            <div class="directory-layout">
                <aside class="directory-sidebar" aria-label="Filtres professionnels">
                    <button class="button button--ghost directory-mobile-filter-toggle" type="button" data-filters-open aria-controls="professionals-filters">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        Filtres
                    </button>
                    <div id="professionals-filters" class="directory-filter-panel">
                        @include('public.partials.professional-filters', ['action' => route('public.professionals.index'), 'idPrefix' => 'professionals-filter'])
                    </div>
                </aside>

                <section class="directory-results" aria-labelledby="professionals-results-title">
                    <div class="directory-results__header">
                        <div>
                            <span class="eyebrow">ANNUAIRE</span>
                            <h2 id="professionals-results-title">{{ $professionals->total() }} professionnel{{ $professionals->total() > 1 ? 's' : '' }}</h2>
                        </div>

                        <span class="directory-results__page">
                            Page {{ $professionals->currentPage() }} / {{ $professionals->lastPage() }}
                        </span>
                    </div>

                    @if ($professionals->isEmpty())
                        <div class="state-empty surface-card">
                            <div>
                                <div class="state-empty__icon"><i class="fa-solid fa-users-slash" aria-hidden="true"></i></div>
                                <h2>Aucun professionnel disponible</h2>
                                <p>Aucun profil ne correspond aux critères actuels. Modifiez les filtres pour élargir la recherche.</p>
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
