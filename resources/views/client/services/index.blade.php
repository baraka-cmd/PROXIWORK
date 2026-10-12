@extends('layouts.client')

@section('title', 'Mes services — PROXIWORK')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/client/services.css')
    @endunless
@endpush

@section('content')
    <div class="client-services-page">
        <header class="client-services-header">
            <div>
                <p class="eyebrow">ESPACE CLIENT · PROXIWORK</p>
                <h1>Mes services</h1>
                <p class="page-lead">
                    Découvrez les prestations de professionnels vérifiés, comparez les offres
                    et retrouvez rapidement le suivi de vos demandes.
                </p>
            </div>
            <a class="button button--primary" href="{{ route('client.requests.index') }}">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                Suivre mes demandes
            </a>
        </header>

        <section class="client-service-stats" aria-label="Vue d'ensemble">
            <a class="client-service-stat" href="#catalogue-services">
                <span class="client-service-stat__icon" aria-hidden="true">
                    <i class="fa-solid fa-briefcase"></i>
                </span>
                <span class="client-service-stat__label">Services disponibles</span>
                <strong>{{ number_format($stats['services'], 0, ',', ' ') }}</strong>
                <span class="client-service-stat__hint">Explorer le catalogue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>

            <a class="client-service-stat" href="#filtrer-services">
                <span class="client-service-stat__icon" aria-hidden="true">
                    <i class="fa-solid fa-layer-group"></i>
                </span>
                <span class="client-service-stat__label">Catégories actives</span>
                <strong>{{ number_format($stats['categories'], 0, ',', ' ') }}</strong>
                <span class="client-service-stat__hint">Affiner ma recherche <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>

            <a class="client-service-stat" href="{{ route('public.professionals.index') }}">
                <span class="client-service-stat__icon" aria-hidden="true">
                    <i class="fa-solid fa-user-check"></i>
                </span>
                <span class="client-service-stat__label">Professionnels vérifiés</span>
                <strong>{{ number_format($stats['professionals'], 0, ',', ' ') }}</strong>
                <span class="client-service-stat__hint">Voir les professionnels <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>

            <a class="client-service-stat" href="{{ route('client.requests.index') }}">
                <span class="client-service-stat__icon" aria-hidden="true">
                    <i class="fa-solid fa-list-check"></i>
                </span>
                <span class="client-service-stat__label">Mes demandes</span>
                <strong>{{ number_format($stats['requests'], 0, ',', ' ') }}</strong>
                <span class="client-service-stat__hint">Consulter mon suivi <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </a>
        </section>

        <section class="client-service-follow-up surface-card" aria-labelledby="client-service-follow-up-title">
            <div class="client-service-section-heading">
                <div>
                    <h2 id="client-service-follow-up-title">Suivi de mes demandes</h2>
                    <p>Ces raccourcis ouvrent la gestion détaillée des demandes dans son espace dédié.</p>
                </div>
                <a class="text-link" href="{{ route('client.requests.index') }}">Tout voir</a>
            </div>

            <nav class="client-service-status-tabs" aria-label="Filtrer mes demandes par statut">
                @foreach ($requestStatuses as $status)
                    <a
                        class="client-service-status-tab"
                        href="{{ $status['value'] === null
                            ? route('client.requests.index')
                            : route('client.requests.index', ['status' => $status['value']]) }}"
                    >
                        <span>{{ $status['label'] }}</span>
                        <strong>{{ number_format($status['count'], 0, ',', ' ') }}</strong>
                    </a>
                @endforeach
            </nav>
        </section>

        <section id="catalogue-services" aria-labelledby="catalogue-services-title">
            <div class="client-service-section-heading client-service-catalogue-heading">
                <div>
                    <p class="eyebrow">CATALOGUE SÉLECTIONNÉ</p>
                    <h2 id="catalogue-services-title">Trouver la bonne prestation</h2>
                    <p>Seuls les services publiés par des professionnels actifs, publics et vérifiés sont affichés.</p>
                </div>
                <span class="client-service-result-count">
                    {{ number_format($services->total(), 0, ',', ' ') }}
                    résultat(s)
                </span>
            </div>

            <form
                id="filtrer-services"
                method="GET"
                action="{{ route('client.services.index') }}"
                class="surface-card client-services-filters"
            >
                <div class="client-services-field client-services-field--search">
                    <label class="form-label" for="service-search">Rechercher</label>
                    <input
                        class="form-control"
                        id="service-search"
                        name="search"
                        type="search"
                        value="{{ $filters['search'] ?? '' }}"
                        maxlength="120"
                        placeholder="Ex. développement web, plomberie..."
                    >
                </div>

                <div class="client-services-field">
                    <label class="form-label" for="service-category">Catégorie</label>
                    <select class="form-control form-select" id="service-category" name="category">
                        <option value="">Toutes les catégories</option>
                        @foreach ($categories as $category)
                            <option
                                value="{{ $category->id }}"
                                @selected((string) ($filters['category'] ?? '') === (string) $category->id)
                            >
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="client-services-field">
                    <label class="form-label" for="service-pricing">Type de tarif</label>
                    <select class="form-control form-select" id="service-pricing" name="pricing">
                        <option value="">Tous les tarifs</option>
                        <option value="fixed" @selected(($filters['pricing'] ?? '') === 'fixed')>Prix fixe</option>
                        <option value="from" @selected(($filters['pricing'] ?? '') === 'from')>À partir de</option>
                        <option value="range" @selected(($filters['pricing'] ?? '') === 'range')>Fourchette de prix</option>
                        <option value="quote" @selected(($filters['pricing'] ?? '') === 'quote')>Sur devis</option>
                    </select>
                </div>

                <div class="client-services-field">
                    <label class="form-label" for="service-city">Ville</label>
                    <input
                        class="form-control"
                        id="service-city"
                        name="city"
                        type="search"
                        value="{{ $filters['city'] ?? '' }}"
                        maxlength="80"
                        placeholder="Ex. Goma"
                    >
                </div>

                <div class="client-services-filter-actions">
                    <button class="button button--primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i>
                        Filtrer
                    </button>
                    <a class="button button--ghost" href="{{ route('client.services.index') }}">Réinitialiser</a>
                </div>
            </form>

            @if ($services->isEmpty())
                <div class="surface-card state-empty client-services-empty">
                    <div>
                        <span class="state-empty__icon">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </span>
                        <h2>Aucun service trouvé</h2>
                        <p>
                            Aucun service visible ne correspond à ces critères.
                            Modifiez les filtres ou réinitialisez la recherche pour explorer le catalogue.
                        </p>
                        <a class="button button--primary" href="{{ route('client.services.index') }}">
                            Voir tous les services
                        </a>
                    </div>
                </div>
            @else
                <div class="service-grid client-services-grid">
                    @foreach ($services as $service)
                        @php
                            $cover = $service->images->firstWhere('is_cover', true)
                                ?? $service->images->first();

                            $professional = $service->professionalProfile;
                            $displayName = $professional?->user?->profile
                                ? trim(
                                    $professional->user->profile->first_name
                                    . ' '
                                    . $professional->user->profile->last_name
                                )
                                : $professional?->user?->name;
                        @endphp

                        <article class="service-card surface-card client-service-card">
                            <div class="service-card__media">
                                @if ($cover)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cover->path) }}"
                                        alt="{{ $cover->alt_text ?: $service->title }}"
                                        loading="lazy"
                                    >
                                @else
                                    <span>
                                        <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                                        <small>Service PROXIWORK</small>
                                    </span>
                                @endif
                                <span class="client-service-availability">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                    Disponible
                                </span>
                            </div>

                            <div class="service-card__body">
                                <div class="client-service-card__identity">
                                    <p class="service-card__category">{{ $service->category?->name ?? 'Service' }}</p>
                                    <span class="client-service-reference">
                                        Réf. SRV-{{ str_pad((string) $service->getKey(), 6, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>

                                <h3>{{ $service->title }}</h3>

                                <p class="client-service-description">
                                    {{ \Illuminate\Support\Str::limit(
                                        $service->short_description
                                            ?: $service->description
                                            ?: 'Découvrez cette prestation proposée sur PROXIWORK.',
                                        180
                                    ) }}
                                </p>

                                <div class="service-card__meta client-service-professional">
                                    <span>
                                        <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                        {{ $displayName ?: 'Professionnel PROXIWORK' }}
                                    </span>
                                    @if ($professional?->city)
                                        <span>
                                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                            {{ $professional->city }}
                                        </span>
                                    @endif
                                    @if ($service->estimated_duration_minutes)
                                        <span>
                                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                            {{ $service->estimated_duration_minutes }} min
                                        </span>
                                    @endif
                                </div>

                                @if ($service->skills->isNotEmpty())
                                    <div class="service-card__skills">
                                        @foreach ($service->skills->take(4) as $skill)
                                            <span class="badge badge--neutral">{{ $skill->name }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="client-service-card__publication">
                                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                    Publié le {{ optional($service->published_at)->format('d/m/Y') ?? '—' }}
                                </div>

                                <div class="service-card__footer client-service-card__footer">
                                    <div class="client-service-price">
                                        @if ($service->pricing_type->value === 'fixed' && $service->price !== null)
                                            <strong>{{ number_format((float) $service->price, 2, ',', ' ') }} {{ $service->currency }}</strong>
                                            <span>Prix fixe</span>
                                        @elseif ($service->pricing_type->value === 'range' && $service->price_min !== null && $service->price_max !== null)
                                            <strong>
                                                {{ number_format((float) $service->price_min, 2, ',', ' ') }}
                                                – {{ number_format((float) $service->price_max, 2, ',', ' ') }}
                                                {{ $service->currency }}
                                            </strong>
                                            <span>Fourchette estimative</span>
                                        @elseif ($service->price_min !== null)
                                            <strong>Dès {{ number_format((float) $service->price_min, 2, ',', ' ') }} {{ $service->currency }}</strong>
                                            <span>Prix de départ</span>
                                        @else
                                            <strong>Sur devis</strong>
                                            <span>Prix à convenir avec le professionnel</span>
                                        @endif
                                    </div>

                                    <div class="service-card__actions">
                                        <a class="button button--ghost" href="{{ route('public.services.show', $service) }}">
                                            Détails
                                        </a>
                                        <a class="button button--primary" href="{{ route('client.requests.create', ['service' => $service->id]) }}">
                                            Demander ce service
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="professional-pagination">
                    {{ $services->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
