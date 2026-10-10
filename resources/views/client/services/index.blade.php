
@extends('layouts.client')

@section('title', 'Catalogue des services — PROXIWORK')

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">CATALOGUE PROXIWORK</p>
            <h1>Découvrez les services</h1>
            <p class="page-lead">
                Trouvez une prestation adaptée à vos besoins et contactez
                un professionnel pour démarrer votre projet.
            </p>
        </div>
    </div>

    <form
        method="GET"
        action="{{ route('client.services.index') }}"
        class="surface-card"
    >
        <div>
            <label class="form-label" for="service-search">
                Rechercher un service
            </label>

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

        <div>
            <label class="form-label" for="service-category">
                Catégorie
            </label>

            <select
                class="form-control form-select"
                id="service-category"
                name="category"
            >
                <option value="">Toutes les catégories</option>

                @foreach ($categories as $category)
                    <option
                        value="{{ $category->id }}"
                        @selected(
                            (string) ($filters['category'] ?? '')
                            === (string) $category->id
                        )
                    >
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <button class="button button--primary" type="submit">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                Rechercher
            </button>

            <a
                class="button button--ghost"
                href="{{ route('client.services.index') }}"
            >
                Réinitialiser
            </a>
        </div>
    </form>

    <section aria-labelledby="services-results-title">
        <div class="dashboard-page-header">
            <div>
                <h2 id="services-results-title">
                    Services disponibles
                </h2>
                <p class="page-lead">
                    {{ $services->total() }}
                    résultat(s) correspondant à votre recherche.
                </p>
            </div>
        </div>

        @if ($services->isEmpty())
            <div class="surface-card state-empty">
                <div>
                    <span class="state-empty__icon">
                        <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                    </span>

                    <h2>Aucun service trouvé</h2>

                    <p>
                        Aucun service publié ne correspond à vos critères.
                        Essayez une autre recherche ou une autre catégorie.
                    </p>

                    <a
                        class="button button--primary"
                        href="{{ route('client.services.index') }}"
                    >
                        Voir tous les services
                    </a>
                </div>
            </div>
        @else
            <div class="service-grid">
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

                    <article class="service-card surface-card">
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
                        </div>

                        <div class="service-card__body">
                            <p class="service-card__category">
                                {{ $service->category?->name ?? 'Service' }}
                            </p>

                            <h3>{{ $service->title }}</h3>

                            <p>
                                {{ \Illuminate\Support\Str::limit(
                                    $service->short_description
                                        ?: $service->description
                                        ?: 'Découvrez cette prestation proposée sur PROXIWORK.',
                                    180
                                ) }}
                            </p>

                            <div class="service-card__meta">
                                <span>
                                    <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                    {{ $displayName ?: 'Professionnel PROXIWORK' }}
                                </span>

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
                                        <span class="badge badge--neutral">
                                            {{ $skill->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="service-card__footer">
                                @if ($service->pricing_type->value === 'fixed'
                                    && $service->price !== null)
                                    <strong>
                                        {{ number_format((float) $service->price, 2, ',', ' ') }}
                                        {{ $service->currency }}
                                    </strong>
                                @elseif ($service->price_min !== null)
                                    <strong>
                                        Dès
                                        {{ number_format((float) $service->price_min, 2, ',', ' ') }}
                                        {{ $service->currency }}
                                    </strong>
                                @else
                                    <strong>Sur devis</strong>
                                @endif

                                <a
                                    class="button button--primary"
                                    href="{{ route('client.requests.create', ['service' => $service->id]) }}"
                                >
                                    Demander ce service
                                </a>
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
@endsection
