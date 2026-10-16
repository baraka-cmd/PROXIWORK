@extends('layouts.public')

@section('title', $service->title.' — PROXIWORK')
@section('meta_description', Str::limit($service->short_description ?: $service->description, 155))

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/service-detail.css')
    @endunless
@endpush

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/public/service-detail.js')
    @endunless
@endpush

@section('content')
    @php
        $professional = $service->professionalProfile;
        $profile = $professional->user->profile;
        $displayName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: $professional->user->name;
        $initials = collect(preg_split('/\s+/u', $displayName) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $hasAvatar = is_string($profile?->avatar_path)
            && $profile->avatar_path !== ''
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($profile->avatar_path);
        $images = $service->images;
        $cover = $images->firstWhere('is_cover', true) ?? $images->first();
        $realizations = $professional->verified_at !== null
            ? $service->realizations
                ->where('status', 'approved')
                ->where('participation_confirmed', true)
            : collect();
        $currency = trim((string) $service->currency);
        $formatPrice = fn ($amount) => number_format((float) $amount, 2, ',', ' ');
        $pricingType = $service->pricing_type->value;
        $durationLabel = null;

        if ($service->estimated_duration_minutes) {
            $hours = intdiv($service->estimated_duration_minutes, 60);
            $minutes = $service->estimated_duration_minutes % 60;
            $durationLabel = collect([
                $hours > 0 ? $hours.' h' : null,
                $minutes > 0 ? $minutes.' min' : null,
            ])->filter()->implode(' ');
        }
    @endphp

    <main class="public-service-page">
        <div class="page-container">
            <nav class="public-service-page__breadcrumbs" aria-label="Fil d’Ariane">
                <a href="{{ route('public.search') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Catalogue des services</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('public.search', ['category' => $service->category->slug]) }}">{{ $service->category->name }}</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ Str::limit($service->title, 48) }}</span>
            </nav>

            <header class="public-service-page__intro">
                <div class="public-service-page__intro-copy">
                    <span class="public-service-page__category"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> {{ $service->category->name }}</span>
                    <h1>{{ $service->title }}</h1>
                    @if (filled($service->short_description))
                        <p class="public-service-page__summary">{{ $service->short_description }}</p>
                    @endif
                    <div class="public-service-page__quick-facts" aria-label="Informations principales">
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            @if ($professional->city || $professional->province)
                                {{ collect([$professional->city, $professional->province])->filter()->join(', ') }}
                            @else
                                Localisation à confirmer
                            @endif
                        </span>
                        @if ($professional->rating_count > 0)
                            <span class="public-service-page__rating"><i class="fa-solid fa-star" aria-hidden="true"></i> <strong>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}</strong> <span>({{ $professional->rating_count }} avis)</span></span>
                        @endif
                        @if ($professional->verification_status?->value === 'verified')
                            <span class="public-service-page__verified"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Professionnel vérifié</span>
                        @endif
                    </div>
                </div>
            </header>

            <div class="public-service-page__layout">
                <div class="public-service-page__content">
                    <section class="public-service-page__gallery surface-card" aria-label="Photos du service">
                        <div class="public-service-page__cover" data-service-media>
                            <span class="public-service-page__image-placeholder" data-service-image-placeholder aria-hidden="true">
                                <i class="fa-solid fa-briefcase" aria-hidden="true"></i><span>Illustration du service</span>
                            </span>
                            @if ($cover)
                                <img class="public-service-page__cover-image" data-service-gallery-main src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}" width="1200" height="675" fetchpriority="high">
                            @endif
                        </div>
                        @if ($images->count() > 1)
                            <div class="public-service-page__thumbnails" role="group" aria-label="Autres photos du service">
                                @foreach ($images->take(6) as $image)
                                    <button class="public-service-page__thumbnail" type="button" data-service-gallery-thumb data-image-src="{{ asset('storage/'.$image->path) }}" data-image-alt="{{ $image->alt_text ?: $service->title }}" aria-label="Afficher la photo {{ $loop->iteration }}" aria-pressed="{{ $image->id === $cover?->id ? 'true' : 'false' }}">
                                        <img src="{{ asset('storage/'.$image->path) }}" alt="" loading="lazy" decoding="async" width="160" height="100">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="public-service-page__panel surface-card" aria-labelledby="service-description-title">
                        <div class="public-service-page__section-heading">
                            <span class="public-service-page__section-icon"><i class="fa-regular fa-file-lines" aria-hidden="true"></i></span>
                            <div><p class="eyebrow">LA PRESTATION</p><h2 id="service-description-title">À propos de ce service</h2></div>
                        </div>
                        <div class="public-service-page__prose"><p>{{ $service->description }}</p></div>
                        @if ($service->skills->isNotEmpty())
                            <div class="public-service-page__skills">
                                <h3>Compétences associées</h3>
                                <ul class="public-service-page__skill-list">
                                    @foreach ($service->skills as $skill)<li>{{ $skill->name }}</li>@endforeach
                                </ul>
                            </div>
                        @endif
                    </section>

                    @foreach ([
                        ['field' => 'included_items', 'title' => 'Ce qui est inclus', 'icon' => 'fa-circle-check', 'class' => 'included'],
                        ['field' => 'excluded_items', 'title' => 'Ce qui n’est pas inclus', 'icon' => 'fa-circle-minus', 'class' => 'excluded'],
                        ['field' => 'client_requirements', 'title' => 'Ce que vous devez préparer', 'icon' => 'fa-clipboard-list', 'class' => 'requirements'],
                        ['field' => 'deliverables', 'title' => 'Résultat et livrables attendus', 'icon' => 'fa-box-open', 'class' => 'deliverables'],
                        ['field' => 'commercial_conditions', 'title' => 'Conditions particulières', 'icon' => 'fa-file-contract', 'class' => 'conditions'],
                    ] as $section)
                        @if (filled($service->{$section['field']}))
                            <section class="public-service-page__panel public-service-page__scope public-service-page__scope--{{ $section['class'] }} surface-card">
                                <div class="public-service-page__section-heading">
                                    <span class="public-service-page__section-icon"><i class="fa-solid {{ $section['icon'] }}" aria-hidden="true"></i></span>
                                    <div><h2>{{ $section['title'] }}</h2></div>
                                </div>
                                <p>{{ $service->{$section['field']} }}</p>
                            </section>
                        @endif
                    @endforeach

                    @if ($realizations->isNotEmpty())
                        <section class="public-service-page__panel surface-card" aria-labelledby="service-realizations-title">
                            <div class="public-service-page__section-heading">
                                <span class="public-service-page__section-icon"><i class="fa-regular fa-images" aria-hidden="true"></i></span>
                                <div>
                                    <p class="eyebrow">EXEMPLES DE TRAVAUX</p>
                                    <h2 id="service-realizations-title">Réalisations approuvées</h2>
                                    <p class="public-service-page__section-description">Des exemples partagés par le professionnel, approuvés par PROXIWORK et associés à une participation confirmée.</p>
                                </div>
                            </div>
                            <div class="public-service-page__realizations">
                                @foreach ($realizations as $realization)
                                    <figure class="public-service-page__realization">
                                        <img src="{{ route('public.service-realizations.image', $realization) }}" alt="{{ $realization->caption ?: 'Réalisation du service '.$service->title }}" loading="lazy" decoding="async" width="640" height="480">
                                        <figcaption>
                                            <span>{{ $realization->caption ?: 'Réalisation du service' }}</span>
                                            @if ($realization->completed_at)<small>Réalisée le {{ $realization->completed_at->format('d/m/Y') }}</small>@endif
                                        </figcaption>
                                    </figure>
                                @endforeach
                            </div>
                            <p class="public-service-page__disclaimer">Ces exemples illustrent des travaux antérieurs et ne constituent pas une garantie absolue d’un résultat identique.</p>
                        </section>
                    @endif
                </div>

                <aside class="public-service-page__sidebar" aria-label="Prix et demande de service">
                    <section class="public-service-page__booking surface-card">
                        <div class="public-service-page__booking-top">
                            <span class="eyebrow">TARIFICATION</span>
                            <span class="public-service-page__execution"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i> {{ $service->execution_mode->label() }}</span>
                        </div>

                        @if ($pricingType === 'quote')
                            <p class="public-service-page__price-label">Tarif personnalisé</p>
                            <p class="public-service-page__price public-service-page__price--quote">Sur devis</p>
                            <p class="public-service-page__price-note">Le professionnel pourra préciser le tarif selon votre besoin.</p>
                        @elseif ($pricingType === 'range' && $service->price_min !== null && $service->price_max !== null)
                            <p class="public-service-page__price-label">Fourchette indicative</p>
                            <p class="public-service-page__price">{{ $formatPrice($service->price_min) }} <span class="public-service-page__price-separator">–</span> {{ $formatPrice($service->price_max) }} @if ($currency)<span class="public-service-page__currency">{{ $currency }}</span>@endif</p>
                        @elseif ($pricingType === 'from' && $service->price !== null)
                            <p class="public-service-page__price-label">À partir de</p>
                            <p class="public-service-page__price">{{ $formatPrice($service->price) }} @if ($currency)<span class="public-service-page__currency">{{ $currency }}</span>@endif</p>
                        @elseif ($pricingType === 'fixed' && $service->price !== null)
                            <p class="public-service-page__price-label">Prix annoncé</p>
                            <p class="public-service-page__price">{{ $formatPrice($service->price) }} @if ($currency)<span class="public-service-page__currency">{{ $currency }}</span>@endif</p>
                        @else
                            <p class="public-service-page__price public-service-page__price--quote">Tarif à confirmer</p>
                            <p class="public-service-page__price-note">Le prix n’est pas renseigné pour le moment.</p>
                        @endif

                        @if ($durationLabel)
                            <div class="public-service-page__booking-detail"><i class="fa-regular fa-clock" aria-hidden="true"></i><span><strong>Durée estimée</strong><span>{{ $durationLabel }}</span></span></div>
                        @endif

                        <div class="public-service-page__booking-divider"></div>
                        <p class="public-service-page__booking-prompt">Un besoin précis ? Envoyez votre demande au professionnel pour discuter des détails.</p>
                        @auth
                            @if (auth()->user()->hasRole('client'))
                                <a class="button button--primary button--lg public-service-page__request-button" href="{{ route('client.requests.create', ['service' => $service->id]) }}">Demander ce service <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            @else
                                <p class="public-service-page__role-note" role="note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Les demandes de service sont réservées aux comptes client.</p>
                            @endif
                        @else
                            <a class="button button--primary button--lg public-service-page__request-button" href="{{ route('client.requests.create', ['service' => $service->id]) }}">Se connecter pour demander <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        @endauth

                        @auth
                            @if (auth()->user()->hasRole('client'))
                                <form method="POST" action="{{ route('client.favorites.services.store', $service) }}" class="public-service-page__favorite-form">
                                    @csrf
                                    <button class="button button--secondary public-service-page__favorite-button" type="submit"><i class="fa-regular fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button>
                                </form>
                            @endif
                        @else
                            <a class="public-service-page__favorite-login" href="{{ route('login') }}"><i class="fa-regular fa-heart" aria-hidden="true"></i> Connectez-vous pour enregistrer ce service</a>
                        @endauth

                        <ul class="public-service-page__trust-list">
                            <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Prestations publiées et visibles sur PROXIWORK</li>
                            <li><i class="fa-solid fa-lock" aria-hidden="true"></i> Votre demande suit le parcours sécurisé de la plateforme</li>
                        </ul>
                    </section>

                    <section class="public-service-page__professional surface-card" aria-labelledby="service-professional-title">
                        <p class="eyebrow">VOTRE PRESTATAIRE</p>
                        <div class="public-service-page__professional-identity">
                            <div class="public-service-page__avatar" data-service-avatar>
                                <span data-service-avatar-initials aria-hidden="true">{{ $initials ?: 'P' }}</span>
                                @if ($hasAvatar)
                                    <img src="{{ route('public.professionals.photo', $professional) }}" alt="Photo de {{ $displayName }}" data-service-avatar-image loading="lazy" width="64" height="64">
                                @endif
                            </div>
                            <div class="public-service-page__professional-name">
                                <h2 id="service-professional-title">{{ $displayName }}</h2>
                                <p>{{ $professional->professional_title ?: 'Professionnel PROXIWORK' }}</p>
                            </div>
                        </div>
                        @if ($professional->city || $professional->province)
                            <p class="public-service-page__professional-location"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</p>
                        @endif
                        @if ($professional->rating_count > 0)
                            <p class="public-service-page__professional-rating"><i class="fa-solid fa-star" aria-hidden="true"></i> <strong>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}</strong> <span>{{ $professional->rating_count }} avis</span></p>
                        @else
                            <p class="public-service-page__no-rating">Aucun avis publié pour le moment.</p>
                        @endif
                        <a class="button button--secondary public-service-page__profile-link" href="{{ route('public.professionals.show', $professional) }}">Voir le profil <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                    </section>
                </aside>
            </div>
        </div>
    </main>
@endsection
