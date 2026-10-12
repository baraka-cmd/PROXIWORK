@extends('layouts.client')

@section('title', 'Mes favoris — PROXIWORK')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/client/favorites-requests-quotes.css', 'resources/css/pages/client/favorites.css'])
    @endunless
@endpush

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/client/favorites-requests-quotes.js')
    @endunless
@endpush

@section('content')
    <main class="favorites-page">
        <header class="favorites-hero">
            <div>
                <p class="favorites-eyebrow">VOTRE SÉLECTION · PROXIWORK</p>
                <h1>Mes favoris</h1>
                <p>Gardez à portée de main les professionnels et les prestations qui vous intéressent, comparez-les, puis revenez à votre projet quand vous êtes prêt.</p>
            </div>
            <a class="button button--primary" href="{{ route('public.professionals.index') }}"><i class="fa-solid fa-compass" aria-hidden="true"></i> Découvrir des professionnels</a>
        </header>

        @if (session('success'))
            <div class="favorites-feedback" role="status" aria-live="polite"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="favorites-feedback favorites-feedback--error" role="alert"><strong>Certains critères n’ont pas été acceptés.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="favorites-stats" aria-label="Résumé de vos favoris">
            <a class="favorites-stat" href="{{ route('client.favorites.index', ['type' => 'all']) }}"><span class="favorites-stat__icon" aria-hidden="true"><i class="fa-solid fa-heart"></i></span><span class="favorites-stat__label">Tous mes favoris</span><strong>{{ number_format($stats['all'], 0, ',', ' ') }}</strong></a>
            <a class="favorites-stat" href="{{ route('client.favorites.index', ['type' => 'professionals']) }}"><span class="favorites-stat__icon" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></span><span class="favorites-stat__label">Professionnels</span><strong>{{ number_format($stats['professionals'], 0, ',', ' ') }}</strong></a>
            <a class="favorites-stat" href="{{ route('client.favorites.index', ['type' => 'services']) }}"><span class="favorites-stat__icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span><span class="favorites-stat__label">Services</span><strong>{{ number_format($stats['services'], 0, ',', ' ') }}</strong></a>
        </section>

        <nav class="favorites-type-tabs" aria-label="Type de favoris">
            @foreach (['all' => 'Tous', 'professionals' => 'Professionnels', 'services' => 'Services'] as $type => $label)
                <a class="favorites-type-tab {{ ($filters['type'] ?? 'all') === $type ? 'is-active' : '' }}"
                   href="{{ route('client.favorites.index', array_filter(['type' => $type, 'search' => $filters['search'] ?? null, 'sort' => $filters['sort'] ?? 'recent'], static fn ($value) => $value !== null && $value !== '')) }}"
                   @if (($filters['type'] ?? 'all') === $type) aria-current="page" @endif>
                    {{ $label }} <strong>{{ $stats[$type] }}</strong>
                </a>
            @endforeach
        </nav>

        <form class="favorites-toolbar" method="GET" action="{{ route('client.favorites.index') }}">
            <input type="hidden" name="type" value="{{ $filters['type'] ?? 'all' }}">
            <div class="favorites-field"><label for="favorites-search">Rechercher dans mes favoris</label><input id="favorites-search" type="search" name="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, service, métier ou ville…"></div>
            <div class="favorites-field"><label for="favorites-sort">Trier par</label><select id="favorites-sort" name="sort"><option value="recent" @selected(($filters['sort'] ?? 'recent') === 'recent')>Ajoutés récemment</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Ajoutés anciennement</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nom (A à Z)</option></select></div>
            <div class="favorite-card__actions"><button class="button button--primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Appliquer</button><a class="button button--ghost" href="{{ route('client.favorites.index', ['type' => $filters['type'] ?? 'all']) }}">Réinitialiser</a></div>
        </form>

        @if ($favorites->isEmpty())
            <section class="favorites-empty">
                <div class="favorites-empty__icon"><i class="fa-regular fa-heart" aria-hidden="true"></i></div>
                @if (($filters['search'] ?? '') !== '')
                    <h2>Aucun résultat pour cette recherche</h2><p>Aucun favori ne correspond à « {{ $filters['search'] }} ». Essayez un autre terme ou réinitialisez les critères.</p><a class="button button--primary" href="{{ route('client.favorites.index', ['type' => $filters['type'] ?? 'all']) }}">Effacer la recherche</a>
                @elseif (($filters['type'] ?? 'all') === 'professionals')
                    <h2>Aucun professionnel favori</h2><p>Enregistrez les professionnels qui vous intéressent depuis leur profil ou l’annuaire public.</p><a class="button button--primary" href="{{ route('public.professionals.index') }}">Explorer les professionnels</a>
                @elseif (($filters['type'] ?? 'all') === 'services')
                    <h2>Aucun service favori</h2><p>Enregistrez les prestations que vous souhaitez comparer depuis le catalogue de services.</p><a class="button button--primary" href="{{ route('public.search') }}">Explorer les services</a>
                @else
                    <h2>Votre sélection commence ici</h2><p>Ajoutez des professionnels et des services pour les retrouver dans un seul espace privé.</p><div class="favorite-card__actions" style="justify-content:center"><a class="button button--primary" href="{{ route('public.professionals.index') }}">Découvrir les professionnels</a><a class="button button--ghost" href="{{ route('public.search') }}">Explorer les services</a></div>
                @endif
            </section>
        @else
            <section class="favorites-grid" aria-label="Vos favoris">
                @foreach ($favorites as $favorite)
                    @if ($favorite->professionalProfile)
                        @php
                            $professional = $favorite->professionalProfile;
                            $user = $professional->user;
                            $profile = $user?->profile;
                            $name = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: ($user?->name ?? 'Professionnel PROXIWORK');
                        @endphp
                        <article class="favorite-card">
                            <div class="favorite-card__media"><div class="favorite-card__media-placeholder"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><small>Professionnel PROXIWORK</small></div></div>
                            <div class="favorite-card__body">
                                <div class="favorite-card__eyebrow"><span>Professionnel</span>@if ($favorite->target_available)<span class="badge badge--success">Vérifié</span>@else<span class="favorite-unavailable"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Indisponible</span>@endif</div>
                                <h2>{{ $name }}</h2><p class="favorite-card__description">{{ $professional->professional_title ?: 'Professionnel PROXIWORK' }}</p>
                                <div class="favorite-card__meta">
                                    @if ($professional->city || $professional->province)<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</span>@endif
                                    <span><i class="fa-solid fa-star" aria-hidden="true"></i>@if ($professional->rating_count > 0) {{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5 ({{ $professional->rating_count }} avis) @else Aucun avis @endif</span>
                                    <span><i class="fa-solid fa-briefcase" aria-hidden="true"></i>{{ $professional->services->count() }} service(s) publié(s)</span>
                                </div>
                                <div class="favorite-card__actions">
                                    @if ($favorite->target_available)<a class="button button--secondary" href="{{ route('public.professionals.show', $professional) }}">Voir le profil</a>@endif
                                    <form method="POST" action="{{ route('client.favorites.destroy', $favorite) }}">@csrf @method('DELETE')<button class="button button--ghost" type="submit" data-confirm-submit="Retirer ce professionnel de vos favoris ?" data-loading-label="Retrait…"><i class="fa-solid fa-heart-crack" aria-hidden="true"></i> Retirer</button></form>
                                </div>
                            </div>
                        </article>
                    @elseif ($favorite->service)
                        @php
                            $service = $favorite->service;
                            $professional = $service->professionalProfile;
                            $cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first();
                            $name = trim(($professional?->user?->profile?->first_name ?? '').' '.($professional?->user?->profile?->last_name ?? '')) ?: ($professional?->user?->name ?? 'Professionnel PROXIWORK');
                        @endphp
                        <article class="favorite-card">
                            <div class="favorite-card__media">
                                @if ($cover)<img src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}" loading="lazy">@else<div class="favorite-card__media-placeholder"><i class="fa-solid fa-briefcase" aria-hidden="true"></i><small>Service PROXIWORK</small></div>@endif
                            </div>
                            <div class="favorite-card__body">
                                <div class="favorite-card__eyebrow"><span>Service</span>@if ($favorite->target_available)<span class="badge badge--success">Publié</span>@else<span class="favorite-unavailable"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Indisponible</span>@endif</div>
                                <h2>{{ $service->title }}</h2><p class="favorite-card__description">{{ \Illuminate\Support\Str::limit($service->short_description ?: $service->description, 150) }}</p>
                                <div class="favorite-card__meta"><span><i class="fa-solid fa-user-tie" aria-hidden="true"></i>{{ $name }}</span>@if ($professional?->city)<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ $professional->city }}</span>@endif @if ($service->category)<span><i class="fa-solid fa-layer-group" aria-hidden="true"></i>{{ $service->category->name }}</span>@endif</div>
                                <div class="favorite-card__price">
                                    @if ($service->pricing_type?->value === 'fixed' && $service->price !== null){{ number_format((float) $service->price, 2, ',', ' ') }} {{ $service->currency }}<small>Prix fixe</small>
                                    @elseif ($service->pricing_type?->value === 'range' && $service->price_min !== null && $service->price_max !== null){{ number_format((float) $service->price_min, 2, ',', ' ') }} – {{ number_format((float) $service->price_max, 2, ',', ' ') }} {{ $service->currency }}<small>Fourchette indicative</small>
                                    @elseif ($service->price_min !== null)Dès {{ number_format((float) $service->price_min, 2, ',', ' ') }} {{ $service->currency }}<small>Prix de départ</small>
                                    @else Sur devis<small>Prix à convenir avec le professionnel</small>@endif
                                </div>
                                <div class="favorite-card__actions">
                                    @if ($favorite->target_available)<a class="button button--secondary" href="{{ route('public.services.show', $service) }}">Voir le service</a><a class="button button--primary" href="{{ route('client.requests.create', ['service' => $service->id]) }}">Demander ce service</a>@endif
                                    <form method="POST" action="{{ route('client.favorites.destroy', $favorite) }}">@csrf @method('DELETE')<button class="button button--ghost" type="submit" data-confirm-submit="Retirer ce service de vos favoris ?" data-loading-label="Retrait…"><i class="fa-solid fa-heart-crack" aria-hidden="true"></i> Retirer</button></form>
                                </div>
                            </div>
                        </article>
                    @endif
                @endforeach
            </section>
            <div class="client-pagination">{{ $favorites->onEachSide(1)->links() }}</div>
        @endif
    </main>
@endsection
