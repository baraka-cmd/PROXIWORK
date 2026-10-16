@extends('layouts.public')

@section('title', 'Professionnels — PROXIWORK')
@section('meta_description', 'Trouvez un professionnel vérifié selon votre métier, votre budget et votre localisation.')

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
    <section class="directory-page directory-page--professionals" data-directory-page>
        <div class="page-container">
            <header class="directory-section-header">
                <div>
                    <span class="eyebrow">RÉSEAU PROXIWORK</span>
                    <h1>Trouvez le bon professionnel pour votre projet</h1>
                    <p>Comparez les spécialités, les compétences, les avis et les tarifs indicatifs de professionnels vérifiés sur PROXIWORK.</p>
                    <p class="directory-section-header__trust"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Des profils publics vérifiés, avec des informations issues de la plateforme.</p>
                </div>

                <a class="button button--primary" href="{{ route('public.search') }}">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    Recherche avancée
                </a>
            </header>

            <div class="directory-layout">
                <aside class="directory-sidebar" aria-label="Filtres professionnels">
                    <button class="button button--ghost directory-mobile-filter-toggle" type="button" data-filters-open aria-controls="professionals-filters" aria-expanded="false">
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
                            Page {{ $professionals->currentPage() }} sur {{ max(1, $professionals->lastPage()) }}
                        </span>
                    </div>

                    @if ($professionals->isEmpty())
                        <div class="state-empty surface-card">
                            <div>
                                <div class="state-empty__icon"><i class="fa-solid fa-users-slash" aria-hidden="true"></i></div>
                                <h2>Aucun professionnel disponible</h2>
                                <p>Aucun profil ne correspond aux critères actuels. Essayez une autre spécialité, élargissez la localisation ou retirez quelques filtres.</p>
                                <a class="button button--primary" href="{{ route('public.professionals.index') }}">Réinitialiser les filtres</a>
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
