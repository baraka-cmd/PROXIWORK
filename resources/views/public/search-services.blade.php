@extends(request()->routeIs('client.search') ? 'layouts.client' : 'layouts.public')
@php
    $searchRoute = request()->routeIs('client.search') ? route('client.search') : route('public.search');
@endphp
@section('page_title', 'Rechercher des services')

@section('title', 'Rechercher un service — PROXIWORK')
@section('meta_description', 'Trouvez un service professionnel publié sur PROXIWORK.')

@php
    $isClientSearch = request()->routeIs('client.search');
    $searchRouteName = $isClientSearch ? 'client.search' : 'public.search';
    $hasSearchParameters = collect($filters ?? [])->contains(fn ($value) => is_array($value) ? $value !== [] : filled($value));
@endphp

@push('head')
    <link rel="canonical" href="{{ route($searchRouteName) }}">
    @if ($isClientSearch || $hasSearchParameters)
        <meta name="robots" content="noindex,follow">
    @endif
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

                @if ($errors->any())
                    <div class="directory-search-alert" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form class="directory-hero__search" method="GET" action="{{ $searchRoute }}" data-service-search-form>
                    @foreach ($filters as $filterName => $filterValue)
                        @if ($filterName !== 'search' && $filterName !== 'page' && filled($filterValue))
                            @if (is_array($filterValue))
                                @foreach ($filterValue as $item)
                                    <input type="hidden" name="{{ $filterName }}[]" value="{{ $item }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                            @endif
                        @endif
                    @endforeach
                    <label class="sr-only" for="service-search">Que recherchez-vous ?</label>
                    <div class="directory-hero__search-field" data-search-autocomplete>
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input id="service-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ex. réparation plomberie, site web..." autocomplete="off" aria-expanded="false" aria-controls="service-search-suggestions">
                        <button class="search-clear-button" type="button" data-search-clear aria-label="Effacer la recherche" hidden><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        <div id="service-search-suggestions" class="search-suggestions" data-search-suggestions role="listbox" hidden></div>
                    </div>
                    <button class="button button--primary button--lg" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Rechercher</button>
                </form>
                <div class="search-history" data-search-history data-search-url="{{ $searchRoute }}" hidden aria-label="Recherches récentes"><span>Recherches récentes</span><div data-search-history-list></div><button type="button" data-search-history-clear>Effacer l’historique</button></div>
            </header>

            <div class="directory-layout">
                <aside class="directory-sidebar">
                    <button class="button button--ghost directory-mobile-filter-toggle" type="button" data-filters-open aria-controls="service-search-filters" aria-expanded="false">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        Filtres
                    </button>
                    <div id="service-search-filters" class="directory-filter-panel">
                        <div class="directory-filters">
                            <div class="directory-filters__header"><div><span class="eyebrow">FILTRES</span><h2>Affiner les services</h2></div><button class="icon-button directory-filters__close" type="button" data-filters-close aria-label="Fermer les filtres"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
                            <form method="GET" action="{{ $searchRoute }}" class="directory-filter-form" data-directory-form>
                                @if (filled($filters['search'] ?? null))
                                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                @endif
                                <div class="form-field"><label class="form-label" for="category">Catégorie</label><select id="category" class="form-control form-select" name="category"><option value="">Toutes les catégories</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
                                <div class="form-field"><span class="form-label">Compétences (dix maximum)</span><div class="directory-skill-list">@foreach($skills as $skill)<label class="directory-check"><input type="checkbox" name="skills[]" value="{{ $skill->slug }}" @checked(in_array($skill->slug, $filters['skills'] ?? [], true))><span>{{ $skill->name }}</span></label>@endforeach</div></div>
                                <div class="directory-filter-grid"><div class="form-field"><label class="form-label" for="city">Ville</label><input id="city" class="form-control" name="city" value="{{ $filters['city'] ?? '' }}"></div><div class="form-field"><label class="form-label" for="province">Province</label><input id="province" class="form-control" name="province" value="{{ $filters['province'] ?? '' }}"></div></div>
                                <div class="form-field"><span class="form-label">Budget</span><div class="directory-filter-grid"><input class="form-control" type="number" min="0" step="0.01" name="min_price" value="{{ $filters['min_price'] ?? '' }}" placeholder="Minimum" aria-label="Tarif minimum dans la devise sélectionnée"><input class="form-control" type="number" min="0" step="0.01" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Maximum" aria-label="Tarif maximum dans la devise sélectionnée"></div><small>Le budget et le tri par prix utilisent les tarifs fixes dans la devise choisie.</small></div>
                                <div class="form-field"><label class="form-label" for="currency">Devise</label><select id="currency" class="form-control form-select" name="currency"><option value="">Toutes</option>@foreach(['USD','CDF','EUR'] as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>@endforeach</select></div>
                                <div class="form-field"><label class="form-label" for="pricing_type">Mode de tarification</label><select id="pricing_type" class="form-control form-select" name="pricing_type"><option value="">Tous les modes</option><option value="fixed" @selected(($filters['pricing_type'] ?? '') === 'fixed')>Prix fixe</option><option value="from" @selected(($filters['pricing_type'] ?? '') === 'from')>À partir de</option><option value="range" @selected(($filters['pricing_type'] ?? '') === 'range')>Fourchette de prix</option><option value="quote" @selected(($filters['pricing_type'] ?? '') === 'quote')>Sur devis</option></select></div>
                                <div class="form-field"><label class="form-label" for="rating">Note minimale</label><select id="rating" class="form-control form-select" name="rating"><option value="">Toutes les notes</option><option value="4" @selected((string) ($filters['rating'] ?? '') === '4')>4 étoiles et plus · au moins trois avis</option><option value="4.5" @selected((string) ($filters['rating'] ?? '') === '4.5')>4,5 étoiles et plus · au moins trois avis</option></select></div>
                                <div class="form-field"><label class="form-label" for="availability">Disponibilité déclarée</label><select id="availability" class="form-control form-select" name="availability"><option value="">Toutes</option><option value="available" @selected(($filters['availability'] ?? '') === 'available')>Disponible</option><option value="unavailable" @selected(($filters['availability'] ?? '') === 'unavailable')>Indisponible</option><option value="unknown" @selected(($filters['availability'] ?? '') === 'unknown')>Non renseignée</option></select></div>
                                <div class="form-field"><label class="form-label" for="sort">Trier par</label><select id="sort" class="form-control form-select" name="sort"><option value="relevance" @selected(($filters['sort'] ?? 'relevance') === 'relevance')>Pertinence</option><option value="rating" @selected(($filters['sort'] ?? '') === 'rating')>Meilleures évaluations</option><option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Prix croissant</option><option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Prix décroissant</option><option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Plus récents</option></select></div>
                                <div class="directory-filter-actions"><a class="button button--ghost" href="{{ $searchRoute }}">Réinitialiser</a><button class="button button--primary" type="submit">Appliquer</button></div>
                            </form>
                        </div>
                    </div>
                </aside>

                <section class="directory-results" aria-labelledby="service-results-title">
                    <div class="directory-results__header"><div><span class="eyebrow">SERVICES PUBLIÉS</span><h2 id="service-results-title">{{ $services->total() }} résultat{{ $services->total() > 1 ? 's' : '' }}</h2></div></div>
                    @php
                        $professionalRouteName = $isClientSearch ? 'client.professionals.show' : 'public.professionals.show';
                        $activeSearchFilters = [];
                        if (filled($filters['category'] ?? null)) {
                            $activeSearchFilters[] = ['label' => $categories->firstWhere('slug', $filters['category'])?->name ?? $filters['category'], 'key' => 'category'];
                        }
                        foreach (['city' => 'Ville', 'province' => 'Province', 'min_price' => 'Prix min.', 'max_price' => 'Prix max.', 'currency' => 'Devise', 'pricing_type' => 'Tarification', 'rating' => 'Note min.', 'availability' => 'Disponibilité', 'sort' => 'Tri'] as $key => $label) {
                            if (filled($filters[$key] ?? null)) {
                                $activeSearchFilters[] = ['label' => $label.': '.$filters[$key], 'key' => $key];
                            }
                        }
                        foreach ($filters['skills'] ?? [] as $skillSlug) {
                            $skill = $skills->firstWhere('slug', $skillSlug);
                            $activeSearchFilters[] = ['label' => 'Compétence : '.($skill?->name ?? $skillSlug), 'skill' => $skillSlug];
                        }
                        if (filled($filters['search'] ?? null)) {
                            $activeSearchFilters[] = ['label' => 'Recherche : '.$filters['search'], 'key' => 'search'];
                        }
                    @endphp
                    @if ($activeSearchFilters !== [])
                        <div class="directory-active-filters" aria-label="Filtres actifs">
                            @foreach ($activeSearchFilters as $activeFilter)
                                @php
                                    $remainingFilters = $filters;
                                    unset($remainingFilters['page']);
                                    if (isset($activeFilter['skill'])) {
                                        $remainingSkills = array_values(array_diff($remainingFilters['skills'] ?? [], [$activeFilter['skill']]));
                                        if ($remainingSkills === []) {
                                            unset($remainingFilters['skills']);
                                        } else {
                                            $remainingFilters['skills'] = $remainingSkills;
                                        }
                                    } else {
                                        unset($remainingFilters[$activeFilter['key']]);
                                    }
                                @endphp
                                <a class="directory-active-filter" href="{{ route($searchRouteName, $remainingFilters) }}">{{ $activeFilter['label'] }} <span aria-hidden="true">×</span><span class="sr-only">Retirer ce filtre</span></a>
                            @endforeach
                            <a class="directory-active-filters__clear" href="{{ route($searchRouteName) }}">Effacer les filtres</a>
                        </div>
                    @endif
                    @if($services->isEmpty())
                        <div class="state-empty surface-card"><div><div class="state-empty__icon"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></div><h2>Aucun service trouvé</h2><p>Essayez une autre recherche ou retirez quelques filtres.</p><a class="button button--ghost" href="{{ $searchRoute }}">Réinitialiser</a></div></div>
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
                                    <div class="public-service-card__body">
                                        <div class="public-service-card__category">{{ $service->category->name }}</div>
                                        @if ($professional->verification_status?->value === 'verified' && $professional->verified_at)
                                            <span class="badge badge--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Professionnel vérifié</span>
                                        @endif
                                        <h3><a href="{{ route('public.services.show', $service) }}">{{ $service->title }}</a></h3>
                                        <p>{{ Str::limit($service->short_description ?: $service->description, 130) }}</p>
                                        <div class="public-service-card__meta">
                                            <span><i class="fa-solid fa-user-tie" aria-hidden="true"></i> <a href="{{ route($professionalRouteName, $professional) }}">{{ $name }}</a></span>
                                            @if ($professional->city)
                                                <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $professional->city }}</span>
                                            @endif
                                            <span><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i> {{ $service->execution_mode->label() }}</span>
                                            @if ($professional->rating_count > 0)
                                                <span aria-label="Note moyenne {{ number_format((float) $professional->rating_average, 1, ',', ' ') }} sur 5, {{ $professional->rating_count }} avis publiés">
                                                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                                                    {{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5 ({{ $professional->rating_count }} avis)
                                                </span>
                                            @endif
                                        </div>
                                        <div class="public-service-card__footer">
                                            <strong>
                                                @if ($service->pricing_type->value === 'quote')
                                                    Sur devis
                                                @elseif ($service->pricing_type->value === 'range')
                                                    {{ number_format((float) $service->price_min, 2, ',', ' ') }} à {{ number_format((float) $service->price_max, 2, ',', ' ') }} {{ $service->currency }}
                                                @elseif ($service->pricing_type->value === 'from')
                                                    À partir de {{ number_format((float) $service->price, 2, ',', ' ') }} {{ $service->currency }}
                                                @elseif ($service->price !== null)
                                                    Prix fixe : {{ number_format((float) $service->price, 2, ',', ' ') }} {{ $service->currency }}
                                                @else
                                                    Tarif à confirmer
                                                @endif
                                            </strong>
                                            <div class="public-service-card__actions">
                                                <a class="button button--primary button--sm" href="{{ route('public.services.show', $service) }}">Voir le service</a>
                                                @auth
                                                    @if (auth()->user()->hasRole('client'))
                                                        @php($favorite = $service->relationLoaded('favorites') ? $service->favorites->first() : null)
                                                        @if ($favorite)
                                                            <form method="POST" action="{{ route('client.favorites.destroy', $favorite) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="button button--secondary button--sm" type="submit"><i class="fa-solid fa-heart" aria-hidden="true"></i> Retirer des favoris</button>
                                                            </form>
                                                        @else
                                                            <form method="POST" action="{{ route('client.favorites.services.store', $service) }}">
                                                                @csrf
                                                                <button class="button button--secondary button--sm" type="submit"><i class="fa-regular fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                @else
                                                    <a class="button button--secondary button--sm" href="{{ route('login', ['continue' => route('public.services.show', $service)]) }}" aria-label="Se connecter pour enregistrer {{ $service->title }} en favori"><i class="fa-solid fa-heart" aria-hidden="true"></i> Favori</a>
                                                @endauth
                                            </div>
                                        </div>
                                    </div>
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
