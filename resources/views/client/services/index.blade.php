@extends('layouts.client')

@section('title', 'Catalogue des services — PROXIWORK')
@section('page_eyebrow', 'CATALOGUE PROXIWORK')
@section('page_title', 'Découvrez les services')
@section('page_description', 'Comparez les prestations publiées par des professionnels vérifiés et choisissez celle qui correspond à votre projet.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/client/services.css')
    @endunless
@endpush

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/client/service-catalog.js')
    @endunless
@endpush

@section('page_actions')
    <a class="button button--secondary" href="{{ route('public.professionals.index') }}"><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Trouver un professionnel</a>
@endsection

@section('content')
    <div class="client-services-page">
        <form method="GET" action="{{ route('client.services.index') }}" class="client-services-filters surface-card" aria-label="Rechercher dans le catalogue des services">
            <div class="client-services-filters__field">
                <label class="form-label" for="service-search">Quel service recherchez-vous ?</label>
                <input class="form-control" id="service-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Ex. développement web, plomberie…">
            </div>
            <div class="client-services-filters__field">
                <label class="form-label" for="service-category">Catégorie</label>
                <select class="form-control form-select" id="service-category" name="category">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="client-services-filters__actions">
                <button class="button button--primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Rechercher</button>
                @if (($filters['search'] ?? '') !== '' || ($filters['category'] ?? '') !== '')
                    <a class="button button--ghost" href="{{ route('client.services.index') }}">Effacer les filtres</a>
                @endif
            </div>
        </form>

        <section class="client-services-results" aria-labelledby="services-results-title">
            <div class="client-services-results__heading">
                <div><span class="eyebrow">OFFRES PUBLIÉES</span><h2 id="services-results-title">Services disponibles</h2></div>
                <p class="client-services-results__count"><strong>{{ number_format($services->total(), 0, ',', ' ') }}</strong> résultat(s)</p>
            </div>

            @if ($services->isEmpty())
                <div class="client-services-empty surface-card">
                    <span class="client-services-empty__icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <h2>Aucun service trouvé</h2>
                    <p>Aucun service publié ne correspond à vos critères. Essayez un autre terme ou explorez toutes les prestations.</p>
                    <a class="button button--primary" href="{{ route('client.services.index') }}">Voir tous les services</a>
                </div>
            @else
                <div class="service-grid">
                    @foreach ($services as $service)
                        @php
                            $cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first();
                            $professional = $service->professionalProfile;
                            $displayName = $professional?->user?->profile
                                ? trim($professional->user->profile->first_name.' '.$professional->user->profile->last_name)
                                : $professional?->user?->name;

                            $priceLabel = match ($service->pricing_type?->value) {
                                'fixed' => $service->price !== null
                                    ? number_format((float) $service->price, 2, ',', ' ').' '.$service->currency
                                    : 'Prix à confirmer',
                                'range' => ($service->price_min !== null && $service->price_max !== null)
                                    ? number_format((float) $service->price_min, 2, ',', ' ').' – '.number_format((float) $service->price_max, 2, ',', ' ').' '.$service->currency
                                    : 'Fourchette à confirmer',
                                'from' => $service->price_min !== null
                                    ? 'Dès '.number_format((float) $service->price_min, 2, ',', ' ').' '.$service->currency
                                    : 'Prix à confirmer',
                                default => 'Sur devis',
                            };
                            $priceHint = match ($service->pricing_type?->value) {
                                'fixed' => 'Prix fixe indiqué',
                                'range' => 'Fourchette indicative',
                                'from' => 'Prix de départ',
                                default => 'Prix convenu avec le professionnel',
                            };
                        @endphp

                        <article class="service-card surface-card">
                            <div class="service-card__media">
                                <span class="service-card__media-fallback" aria-hidden="true"><i class="fa-solid fa-briefcase"></i><small>Service PROXIWORK</small></span>
                                @if ($cover)
                                    <img data-service-cover src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}" loading="lazy" decoding="async">
                                @endif
                                <span class="service-card__category-badge">{{ $service->category?->name ?? 'Service' }}</span>
                            </div>

                            <div class="service-card__body">
                                <div class="service-card__heading">
                                    <h3>{{ $service->title }}</h3>
                                    <p>{{ \Illuminate\Support\Str::limit($service->short_description ?: $service->description ?: 'Découvrez cette prestation proposée sur PROXIWORK.', 150) }}</p>
                                </div>

                                <div class="service-card__meta">
                                    <span><i class="fa-solid fa-user-tie" aria-hidden="true"></i>{{ $displayName ?: 'Professionnel PROXIWORK' }}</span>
                                    @if ($service->estimated_duration_minutes)
                                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $service->estimated_duration_minutes }} min estimées</span>
                                    @endif
                                </div>

                                @if ($service->skills->isNotEmpty())
                                    <div class="service-card__skills" aria-label="Compétences associées">
                                        @foreach ($service->skills->take(3) as $skill)
                                            <span class="badge badge--neutral">{{ $skill->name }}</span>
                                        @endforeach
                                        @if ($service->skills->count() > 3)<span class="badge badge--neutral">+{{ $service->skills->count() - 3 }}</span>@endif
                                    </div>
                                @endif

                                <div class="service-card__footer">
                                    <div class="service-card__price"><strong>{{ $priceLabel }}</strong><small>{{ $priceHint }}</small></div>
                                    <div class="service-card__actions">
                                        <a class="button button--secondary" href="{{ route('public.services.show', $service) }}">Voir le détail</a>
                                        <a class="button button--primary" href="{{ route('client.requests.create', ['service' => $service->id]) }}">Demander ce service</a>
                                        <form method="POST" action="{{ route('client.favorites.services.store', $service) }}">
                                            @csrf
                                            <button class="button button--ghost service-card__favorite" type="submit" aria-label="Ajouter {{ $service->title }} aux favoris"><i class="fa-regular fa-heart" aria-hidden="true"></i><span>Favori</span></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="client-services-pagination" aria-label="Pagination des services">{{ $services->onEachSide(1)->links() }}</div>
            @endif
        </section>
    </div>
@endsection
