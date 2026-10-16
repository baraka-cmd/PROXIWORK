@extends('layouts.public')

@section('title', 'Rechercher un service — PROXIWORK')
@section('meta_description', 'Trouvez un service professionnel publié sur PROXIWORK.')

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
                    <span class="eyebrow">CATALOGUE PROXIWORK</span>
                    <h1>Trouvez le bon service pour votre projet.</h1>
                    <p>Comparez les prestations publiées par des professionnels vérifiés et contactez le bon prestataire.</p>
                </div>

                <form class="directory-hero__search" method="GET" action="{{ route('public.search') }}" data-service-search-form>
                    <label class="sr-only" for="service-search">Que recherchez-vous ?</label>
                    <div class="directory-hero__search-field" data-search-autocomplete>
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input id="service-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ex. réparation plomberie, site web..." autocomplete="off" aria-expanded="false" aria-controls="service-search-suggestions">
                        <button class="search-clear-button" type="button" data-search-clear aria-label="Effacer la recherche" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        <div id="service-search-suggestions" class="search-suggestions" data-search-suggestions role="listbox" hidden></div>
                    </div>
                    <button class="button button--primary button--lg" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Rechercher</button>
                </form>
                <div class="search-history" data-search-history hidden aria-label="Recherches récentes"><span>Recherches récentes</span><div data-search-history-list></div><button type="button" data-search-history-clear>Effacer l’historique</button></div>
            </header>

            <div class="directory-layout">
                <aside class="directory-sidebar">
                    <button class="button button--secondary directory-mobile-filter-toggle" type="button" data-filters-open aria-expanded="false" aria-controls="service-filter-panel"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Affiner les résultats</button>
                    <div class="directory-filter-panel" id="service-filter-panel">
                        <div class="directory-filters">
                            <div class="directory-filters__header"><div><span class="eyebrow">FILTRES</span><h2>Affiner les services</h2><p class="directory-filters__description">Précisez votre besoin pour trouver une prestation adaptée.</p></div><button class="directory-filters__close button button--ghost button--sm" type="button" data-filters-close aria-label="Fermer les filtres"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
                            <form method="GET" action="{{ route('public.search') }}" class="directory-filter-form" data-directory-form>
                                @if(!empty($filters['search']))<input type="hidden" name="search" value="{{ $filters['search'] }}">@endif
                                <div class="form-field"><label class="form-label" for="category">Catégorie</label><select id="category" class="form-control form-select" name="category"><option value="">Toutes les catégories</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
                                <div class="form-field"><span class="form-label">Compétences</span><div class="directory-skill-list">@foreach($skills->take(20) as $skill)<label class="directory-check"><input type="checkbox" name="skills[]" value="{{ $skill->slug }}" @checked(in_array($skill->slug, $filters['skills'] ?? [], true))><span>{{ $skill->name }}</span></label>@endforeach</div></div>
                                <div class="directory-filter-grid"><div class="form-field"><label class="form-label" for="city">Ville</label><input id="city" class="form-control" name="city" value="{{ $filters['city'] ?? '' }}"></div><div class="form-field"><label class="form-label" for="province">Province</label><input id="province" class="form-control" name="province" value="{{ $filters['province'] ?? '' }}"></div></div>
                                <fieldset class="form-field directory-budget-field"><legend class="form-label">Budget</legend><div class="directory-filter-grid"><div><label class="sr-only" for="min-price">Prix minimum</label><input id="min-price" class="form-control" type="number" min="0" step="0.01" name="min_price" value="{{ $filters['min_price'] ?? '' }}" placeholder="Minimum" inputmode="decimal"></div><div><label class="sr-only" for="max-price">Prix maximum</label><input id="max-price" class="form-control" type="number" min="0" step="0.01" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Maximum" inputmode="decimal"></div></div></fieldset>
                                <div class="form-field"><label class="form-label" for="currency">Devise</label><select id="currency" class="form-control form-select" name="currency"><option value="">Toutes</option>@foreach(['USD','CDF','EUR'] as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>@endforeach</select></div>
                                <div class="form-field"><label class="form-label" for="sort">Trier par</label><select id="sort" class="form-control form-select" name="sort"><option value="relevance">Pertinence</option><option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Prix croissant</option><option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Prix décroissant</option><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Plus récents</option></select></div>
                                <div class="directory-filter-actions"><a class="button button--ghost" href="{{ route('public.search') }}">Réinitialiser</a><button class="button button--primary" type="submit">Appliquer</button></div>
                            </form>
                        </div>
                    </div>
                </aside>

                <section class="directory-results" aria-labelledby="service-results-title">
                    <div class="directory-results__header"><div><span class="eyebrow">CATALOGUE PROXIWORK</span><h2 id="service-results-title">{{ $services->total() }} résultat{{ $services->total() > 1 ? 's' : '' }}</h2><p class="directory-results__context">@if(!empty($filters['search']))Résultats pour <strong>« {{ $filters['search'] }} »</strong>@else Découvrez les prestations de professionnels vérifiés.@endif</p></div><form class="directory-results__sort" method="GET" action="{{ route('public.search') }}" aria-label="Trier les services">@foreach(['search', 'category', 'city', 'province', 'min_price', 'max_price', 'currency', 'per_page'] as $field)@if(isset($filters[$field]) && $filters[$field] !== '')<input type="hidden" name="{{ $field }}" value="{{ $filters[$field] }}">@endif @endforeach @foreach(($filters['skills'] ?? []) as $skill)<input type="hidden" name="skills[]" value="{{ $skill }}">@endforeach<label class="form-label" for="results-sort">Trier par</label><select id="results-sort" class="form-control form-select" name="sort" data-results-sort><option value="relevance" @selected(($filters['sort'] ?? 'relevance') === 'relevance')>Pertinence</option><option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Prix croissant</option><option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Prix décroissant</option><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Plus récents</option></select></form></div>
                    @if($services->isEmpty())
                        <div class="state-empty surface-card"><div><div class="state-empty__icon"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></div><h2>Aucun service trouvé</h2><p>Essayez une autre recherche ou retirez quelques filtres.</p><a class="button button--ghost" href="{{ route('public.search') }}">Réinitialiser</a></div></div>
                    @else
                        <div class="public-service-grid">
                            @foreach($services as $service)
                                @php($cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first())
                                @php($professional = $service->professionalProfile)
                                @php($name = trim(($professional->user->profile?->first_name ?? '').' '.($professional->user->profile?->last_name ?? '')) ?: $professional->user->name)
                                <article class="public-service-card surface-card">
                                    <a class="public-service-card__media" href="{{ route('public.services.show', $service) }}">
                                        @if($cover)<img src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}" loading="lazy">@else<span><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>@endif
                                    </a>
                                    <div class="public-service-card__body"><div class="public-service-card__category">{{ $service->category->name }}</div><h3><a href="{{ route('public.services.show', $service) }}">{{ $service->title }}</a></h3><p>{{ Str::limit($service->short_description ?: $service->description, 130) }}</p><div class="public-service-card__meta"><span><i class="fa-solid fa-user-tie" aria-hidden="true"></i> {{ $name }}</span>@if($professional->city)<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $professional->city }}</span>@endif</div><div class="public-service-card__footer"><strong class="public-service-card__price">@if($service->pricing_type->value === 'quote') Sur devis @elseif($service->pricing_type->value === 'range') {{ number_format((float)$service->price_min, 2, ',', ' ') }}–{{ number_format((float)$service->price_max, 2, ',', ' ') }} {{ $service->currency }} @else {{ number_format((float)$service->price, 2, ',', ' ') }} {{ $service->currency }} @endif</strong><div class="public-service-card__actions"><a class="button button--primary button--sm" href="{{ route('public.services.show', $service) }}">Voir le service</a>@auth @if(auth()->user()->hasRole('client'))<form method="POST" action="{{ route('client.favorites.services.store', $service) }}">@csrf<button class="button button--secondary button--sm" type="submit"><i class="fa-solid fa-heart" aria-hidden="true"></i> Favori</button></form>@endif @else<a class="button button--secondary button--sm" href="{{ route('login') }}" aria-label="Se connecter pour enregistrer {{ $service->title }} en favori"><i class="fa-solid fa-heart" aria-hidden="true"></i></a>@endauth</div></div></div>
                                </article>
                            @endforeach
                        </div>
                        <div class="directory-pagination">{{ $services->onEachSide(1)->links() }}</div>
                    @endif
                </section>
            </div>
        </div>
    </section>
@endsection