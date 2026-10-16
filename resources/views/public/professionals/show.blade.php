@extends('layouts.public')

@section('title', $professional->professional_title ?: $professional->user->name.' — PROXIWORK')
@section('meta_description', Str::limit($professional->description ?: 'Découvrez les services, compétences et avis publiés de ce professionnel sur PROXIWORK.', 155))

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/professionals.css')
    @endunless
@endpush

@section('content')
    @php
        $profile = $professional->user->profile;
        $displayName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: $professional->user->name;
        $initials = collect(preg_split('/\s+/', trim($displayName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $location = collect([$professional->city, $professional->province])->filter()->join(', ');
        $hasAvatar = $profile?->avatar_path
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($profile->avatar_path);
    @endphp

    <main class="page-container public-professional-detail public-professional-profile">
        <nav class="professional-profile__breadcrumb" aria-label="Fil d’Ariane">
            <a class="button button--ghost" href="{{ route('public.professionals.index') }}">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Retour à l’annuaire
            </a>
            <span>Profil professionnel</span>
        </nav>

        <header class="surface-card public-professional-detail__hero professional-profile__hero">
            <div class="professional-profile__portrait" aria-label="Portrait de {{ $displayName }}">
                @if ($hasAvatar)
                    <img
                        class="professional-profile__photo"
                        src="{{ route('public.professionals.photo', $professional) }}"
                        alt="Photo de {{ $displayName }}"
                        width="144"
                        height="144"
                        fetchpriority="high"
                    >
                @else
                    <span class="professional-profile__initials" aria-hidden="true">{{ $initials ?: 'P' }}</span>
                @endif
            </div>

            <div class="public-professional-detail__identity professional-profile__identity">
                <div class="professional-profile__badges">
                    @if ($professional->verification_status?->value === 'verified')
                        <span class="badge badge--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Profil vérifié</span>
                    @endif
                    @if ($professional->availability_status?->value === 'available')
                        <span class="badge badge--primary"><span class="badge__dot" aria-hidden="true"></span> Disponible</span>
                    @endif
                </div>

                <p class="eyebrow">PROFESSIONNEL PROXIWORK</p>
                <h1>{{ $displayName }}</h1>
                <p class="professional-profile__title">{{ $professional->professional_title ?: 'Professionnel de services' }}</p>

                <div class="professional-profile__quick-facts">
                    @if ($location !== '')
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ $location }}</span>
                    @endif
                    @if ($professional->years_experience !== null)
                        <span><i class="fa-solid fa-briefcase" aria-hidden="true"></i>{{ $professional->years_experience }} {{ $professional->years_experience > 1 ? 'ans' : 'an' }} d’expérience déclarée</span>
                    @endif
                    @if ($executionModes->isNotEmpty())
                        <span><i class="fa-solid fa-laptop-house" aria-hidden="true"></i>{{ $executionModes->join(' · ') }}</span>
                    @endif
                </div>
            </div>

            <div class="professional-profile__hero-summary" aria-label="Résumé du profil">
                <div class="professional-profile__rating-summary">
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    @if ($professional->rating_count > 0)
                        <strong>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}<span>/5</span></strong>
                        <small>{{ $professional->rating_count }} avis publiés</small>
                    @else
                        <strong>Nouvel avis à venir</strong>
                        <small>Aucun avis publié pour le moment</small>
                    @endif
                </div>
                @if ($professional->starting_price !== null && $professional->currency)
                    <div class="professional-profile__starting-price">
                        <small>Tarif indicatif à partir de</small>
                        <strong>{{ number_format((float) $professional->starting_price, 2, ',', ' ') }} {{ $professional->currency }}</strong>
                    </div>
                @endif
            </div>
        </header>

        <div class="public-professional-detail__grid professional-profile__layout">
            <div class="professional-profile__content">
                <section class="surface-card professional-profile__section" aria-labelledby="professional-about-title">
                    <div class="professional-profile__section-heading">
                        <span class="professional-profile__section-icon"><i class="fa-regular fa-user" aria-hidden="true"></i></span>
                        <div>
                            <p class="eyebrow">EN QUELQUES MOTS</p>
                            <h2 id="professional-about-title">Présentation</h2>
                        </div>
                    </div>
                    @if (filled($professional->description))
                        <p class="professional-profile__bio">{{ $professional->description }}</p>
                    @else
                        <p class="professional-profile__empty">Ce professionnel n’a pas encore ajouté de présentation.</p>
                    @endif
                </section>

                <section class="surface-card professional-profile__section" aria-labelledby="professional-services-title">
                    <div class="professional-profile__section-heading">
                        <span class="professional-profile__section-icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>
                        <div>
                            <p class="eyebrow">CE QU’IL PROPOSE</p>
                            <h2 id="professional-services-title">Services publiés <span class="professional-profile__count">{{ $professional->services->count() }}</span></h2>
                            <p class="professional-profile__section-intro">Découvrez les prestations disponibles et leurs conditions.</p>
                        </div>
                    </div>

                    <div class="professional-profile__services">
                        @forelse ($professional->services as $service)
                            @php
                                $cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first();
                                $servicePrice = match ($service->pricing_type?->value) {
                                    'fixed' => $service->price !== null
                                        ? number_format((float) $service->price, 2, ',', ' ').' '.($service->currency ?? '')
                                        : 'Prix à confirmer',
                                    'from' => $service->price !== null
                                        ? 'Dès '.number_format((float) $service->price, 2, ',', ' ').' '.($service->currency ?? '')
                                        : 'Prix à confirmer',
                                    'range' => $service->price_min !== null && $service->price_max !== null
                                        ? number_format((float) $service->price_min, 2, ',', ' ').' – '.number_format((float) $service->price_max, 2, ',', ' ').' '.($service->currency ?? '')
                                        : 'Prix à confirmer',
                                    'quote' => 'Sur devis',
                                    default => 'Tarif à confirmer',
                                };
                            @endphp
                            <article class="professional-profile__service-card">
                                <a class="professional-profile__service-media" href="{{ route('public.services.show', $service) }}" tabindex="-1" aria-hidden="true">
                                    @if ($cover)
                                        <img src="{{ asset('storage/'.$cover->path) }}" alt="" loading="lazy" decoding="async">
                                    @else
                                        <span><i class="fa-solid fa-toolbox" aria-hidden="true"></i></span>
                                    @endif
                                </a>
                                <div class="professional-profile__service-copy">
                                    @if ($service->category)
                                        <span class="professional-profile__category">{{ $service->category->name }}</span>
                                    @endif
                                    <h3><a href="{{ route('public.services.show', $service) }}">{{ $service->title }}</a></h3>
                                    <p>{{ \Illuminate\Support\Str::limit($service->short_description ?: $service->description, 150) }}</p>
                                    @if ($service->skills->isNotEmpty())
                                        <div class="professional-profile__service-skills" aria-label="Compétences liées au service">
                                            @foreach ($service->skills->take(3) as $skill)
                                                <span class="badge badge--neutral">{{ $skill->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="professional-profile__service-footer">
                                        <span class="professional-profile__service-price">{{ $servicePrice }}</span>
                                        <a class="professional-profile__service-link" href="{{ route('public.services.show', $service) }}">Voir la prestation <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="professional-profile__empty-state">
                                <span><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span>
                                <h3>Aucun service publié pour le moment</h3>
                                <p>Les prestations seront présentées ici lorsqu’elles seront disponibles.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="surface-card professional-profile__section" aria-labelledby="professional-skills-title">
                    <div class="professional-profile__section-heading">
                        <span class="professional-profile__section-icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
                        <div>
                            <p class="eyebrow">SAVOIR-FAIRE</p>
                            <h2 id="professional-skills-title">Compétences</h2>
                        </div>
                    </div>
                    @if ($professional->skills->isNotEmpty())
                        <div class="professional-profile__chips">
                            @foreach ($professional->skills as $skill)
                                <span class="badge badge--neutral">{{ $skill->name }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="professional-profile__empty">Aucune compétence supplémentaire n’a été renseignée.</p>
                    @endif
                </section>

                <section class="surface-card professional-profile__section" aria-labelledby="professional-languages-title">
                    <div class="professional-profile__section-heading">
                        <span class="professional-profile__section-icon"><i class="fa-solid fa-language" aria-hidden="true"></i></span>
                        <div>
                            <p class="eyebrow">COMMUNICATION</p>
                            <h2 id="professional-languages-title">Langues</h2>
                        </div>
                    </div>
                    @if (! empty($professional->languages))
                        <div class="professional-profile__chips" aria-label="Langues de communication">
                            @foreach ($professional->languages as $language)
                                <span class="badge badge--neutral">{{ $language }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="professional-profile__empty">Les langues de communication ne sont pas renseignées.</p>
                    @endif
                </section>

                <section class="surface-card professional-profile__section" aria-labelledby="professional-reviews-title">
                    <div class="professional-profile__section-heading professional-profile__reviews-heading">
                        <span class="professional-profile__section-icon"><i class="fa-regular fa-star" aria-hidden="true"></i></span>
                        <div>
                            <p class="eyebrow">RETOURS CLIENTS</p>
                            <h2 id="professional-reviews-title">Avis publiés</h2>
                            <p class="professional-profile__section-intro">Les évaluations ci-dessous sont celles publiées sur PROXIWORK.</p>
                        </div>
                        @if ($professional->rating_count > 0)
                            <div class="professional-profile__review-score"><strong>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}</strong><span>/5</span></div>
                        @endif
                    </div>
                    <div class="public-profile-reviews professional-profile__reviews">
                        @forelse ($professional->reviews as $review)
                            <article class="public-review-card professional-profile__review">
                                <div class="public-review-card__heading">
                                    <strong><span class="professional-profile__review-stars" aria-label="{{ $review->rating }} sur 5">{{ $review->rating }}/5 <span aria-hidden="true">★</span></span></strong>
                                    <time datetime="{{ $review->published_at?->toDateString() }}">{{ $review->published_at?->format('d/m/Y') }}</time>
                                </div>
                                @if (filled($review->comment))
                                    <p>{{ $review->comment }}</p>
                                @else
                                    <p class="public-review-card__muted">Une note a été publiée sans commentaire.</p>
                                @endif
                                @if ($review->order?->serviceRequest?->service)
                                    <small class="professional-profile__review-service">Prestation : {{ $review->order->serviceRequest->service->title }}</small>
                                @endif
                                <small>Avis associé à une commande PROXIWORK</small>
                                @if ($review->response?->status === \App\Enums\ReviewStatus::PUBLISHED)
                                    <div class="public-review-card__response">
                                        <strong>Réponse du professionnel</strong>
                                        <p>{{ $review->response->response }}</p>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="professional-profile__empty-state">
                                <span><i class="fa-regular fa-comment-dots" aria-hidden="true"></i></span>
                                <h3>Pas encore d’avis publié</h3>
                                <p>Les retours apparaîtront ici après leur publication sur la plateforme.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="professional-profile__sidebar" aria-label="Contacter le professionnel">
                <section class="surface-card professional-profile__contact-card">
                    <p class="eyebrow">VOTRE PROJET</p>
                    <h2>Parlons de votre besoin</h2>
                    <p>Consultez les prestations disponibles ou contactez ce professionnel pour échanger sur votre projet.</p>

                    @if ($professional->starting_price !== null && $professional->currency)
                        <div class="professional-profile__contact-price">
                            <small>Tarif indicatif à partir de</small>
                            <strong>{{ number_format((float) $professional->starting_price, 2, ',', ' ') }} {{ $professional->currency }}</strong>
                        </div>
                    @endif

                    @auth
                        @if (auth()->user()->hasRole('client'))
                            <form method="POST" action="{{ route('client.messages.start', $professional) }}" class="professional-profile__contact-form">
                                @csrf
                                <button class="button button--primary" type="submit"><i class="fa-regular fa-comments" aria-hidden="true"></i> Contacter ce professionnel</button>
                            </form>
                            <form method="POST" action="{{ route('client.favorites.store', $professional) }}" class="professional-profile__contact-form">
                                @csrf
                                @method('PUT')
                                <button class="button button--secondary" type="submit"><i class="fa-regular fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button>
                            </form>
                        @elseif (! auth()->user()->hasRole('professional') && ! auth()->user()->hasRole('admin'))
                            <p class="professional-profile__role-note">La prise de contact et les favoris sont disponibles depuis un compte client.</p>
                        @endif
                    @else
                        <a class="button button--primary" href="{{ route('login') }}"><i class="fa-regular fa-comments" aria-hidden="true"></i> Se connecter pour contacter</a>
                        <a class="button button--secondary" href="{{ route('login') }}"><i class="fa-regular fa-heart" aria-hidden="true"></i> Se connecter pour enregistrer</a>
                    @endauth

                    <div class="professional-profile__contact-assurance">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <span>Échangez via les parcours sécurisés de PROXIWORK.</span>
                    </div>
                </section>

                <section class="surface-card professional-profile__facts-card">
                    <h2>Informations du profil</h2>
                    <dl>
                        @if ($location !== '')
                            <div><dt><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Zone</dt><dd>{{ $location }}</dd></div>
                        @endif
                        @if ($professional->years_experience !== null)
                            <div><dt><i class="fa-solid fa-briefcase" aria-hidden="true"></i> Expérience</dt><dd>{{ $professional->years_experience }} {{ $professional->years_experience > 1 ? 'ans' : 'an' }}</dd></div>
                        @endif
                        @if ($professional->service_radius_km)
                            <div><dt><i class="fa-solid fa-compass" aria-hidden="true"></i> Rayon indicatif</dt><dd>{{ $professional->service_radius_km }} km</dd></div>
                        @endif
                        @if ($executionModes->isNotEmpty())
                            <div><dt><i class="fa-solid fa-laptop-house" aria-hidden="true"></i> Prestation</dt><dd>{{ $executionModes->join(', ') }}</dd></div>
                        @endif
                        <div><dt><i class="fa-solid fa-toolbox" aria-hidden="true"></i> Services</dt><dd>{{ $professional->services->count() }} publié(s)</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </main>
@endsection
