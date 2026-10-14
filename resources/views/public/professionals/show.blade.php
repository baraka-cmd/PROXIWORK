@extends('layouts.public')

@section('title', $professional->professional_title ?: $professional->user->name.' — PROXIWORK')
@section('meta_description', Str::limit($professional->description ?: 'Profil professionnel PROXIWORK.', 155))

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/professionals.css')
    @endunless
@endpush

@section('content')
    @php
        $profile = $professional->user->profile;
        $displayName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: $professional->user->name;
        $hasAvatar = $profile?->avatar_path
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($profile->avatar_path);
    @endphp

    <main class="page-container public-professional-detail">
        <a class="button button--ghost" href="{{ route('public.professionals.index') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à l’annuaire
        </a>

        <header class="surface-card public-professional-detail__hero">
            @if ($hasAvatar)
                <img class="avatar avatar--xl professional-profile-avatar" src="{{ route('public.professionals.photo', $professional) }}" alt="Photo de {{ $displayName }}" loading="lazy">
            @else
                <div class="avatar avatar--xl" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</div>
            @endif

            <div class="public-professional-detail__identity">
                <div class="professional-card__badges">
                    @if ($professional->verification_status?->value === 'verified')
                        <span class="badge badge--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Vérifié</span>
                    @endif
                    @if ($professional->availability_status?->value === 'available')
                        <span class="badge badge--primary">Disponible</span>
                    @endif
                </div>
                <h1>{{ $displayName }}</h1>
                <p>{{ $professional->professional_title ?: 'Professionnel PROXIWORK' }}</p>
                <div class="professional-meta">
                    @if ($professional->city || $professional->province)
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</span>
                    @endif
                    @if ($professional->years_experience !== null)
                        <span><i class="fa-solid fa-briefcase" aria-hidden="true"></i> {{ $professional->years_experience }} an(s) d’expérience déclarée</span>
                    @endif
                    @if ($professional->service_radius_km)
                        <span><i class="fa-solid fa-compass" aria-hidden="true"></i> Rayon d’intervention indicatif : {{ $professional->service_radius_km }} km</span>
                    @endif
                    @if ($professional->starting_price !== null && $professional->currency)
                        <span><i class="fa-solid fa-tag" aria-hidden="true"></i> Prix indicatif dès {{ number_format((float) $professional->starting_price, 2, ',', ' ') }} {{ $professional->currency }}</span>
                    @endif
                    @if ($executionModes->isNotEmpty())
                        <span><i class="fa-solid fa-laptop-house" aria-hidden="true"></i> Modes de prestation : {{ $executionModes->join(', ') }}</span>
                    @endif
                </div>
            </div>
        </header>

        <div class="public-professional-detail__grid">
            <section class="surface-card public-professional-detail__main">
                <h2>Présentation professionnelle</h2>
                @if (filled($professional->description))
                    <p class="profile-bio">{{ $professional->description }}</p>
                @else
                    <p>Aucune présentation professionnelle publiée pour le moment.</p>
                @endif

                <h2>Langues de communication</h2>
                @if (! empty($professional->languages))
                    <div class="public-service-card__skills" aria-label="Langues parlées">
                        @foreach ($professional->languages as $language)
                            <span class="badge badge--neutral">{{ $language }}</span>
                        @endforeach
                    </div>
                @else
                    <p>Langues non renseignées.</p>
                @endif

                <h2>Compétences</h2>
                <div class="public-service-card__skills">
                    @forelse ($professional->skills as $skill)
                        <span class="badge badge--neutral">{{ $skill->name }}</span>
                    @empty
                        <p>Aucune compétence publiée.</p>
                    @endforelse
                </div>

                <h2>Services publiés</h2>
                <div class="public-profile-services">
                    @forelse ($professional->services as $service)
                        <article>
                            <h3><a href="{{ route('public.services.show', $service) }}">{{ $service->title }}</a></h3>
                            <p>{{ Str::limit($service->short_description ?: $service->description, 120) }}</p>
                        </article>
                    @empty
                        <p>Aucun service publié.</p>
                    @endforelse
                </div>

                <h2>Avis publiés</h2>
                <p>Les avis affichés ici sont associés à une commande et ont été publiés après modération éventuelle.</p>
                <div class="public-profile-reviews">
                    @forelse ($professional->reviews as $review)
                        <article class="public-review-card">
                            <div class="public-review-card__heading">
                                <strong>{{ $review->rating }}/5 <span aria-hidden="true">★</span></strong>
                                <small>{{ $review->published_at?->format('d/m/Y') }}</small>
                            </div>
                            @if (filled($review->comment))
                                <p>{{ $review->comment }}</p>
                            @else
                                <p class="public-review-card__muted">Note publiée sans commentaire.</p>
                            @endif
                            @if ($review->order?->serviceRequest?->service)
                                <small>Prestation : {{ $review->order->serviceRequest->service->title }}</small>
                            @endif
                            <small>Client ayant réalisé une commande PROXIWORK</small>
                            @if ($review->response?->status === \App\Enums\ReviewStatus::PUBLISHED)
                                <div class="public-review-card__response">
                                    <strong>Réponse du professionnel</strong>
                                    <p>{{ $review->response->response }}</p>
                                </div>
                            @endif
                        </article>
                    @empty
                        <p>Aucun avis publié pour le moment.</p>
                    @endforelse
                </div>
            </section>

            <aside class="surface-card public-professional-detail__aside">
                <span class="eyebrow">RÉPUTATION</span>
                @if ($professional->rating_count > 0)
                    <h2>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5</h2>
                    <p>{{ $professional->rating_count }} avis publiés</p>
                @else
                    <h2>Aucun avis</h2>
                    <p>Ce professionnel n’a pas encore reçu d’avis publié.</p>
                @endif

                @auth
                    @if (auth()->user()->hasRole('client'))
                        <form method="POST" action="{{ route('client.messages.start', $professional) }}" class="public-professional-contact">
                            @csrf
                            <button class="button button--primary" type="submit"><i class="fa-regular fa-comments" aria-hidden="true"></i> Contacter ce professionnel</button>
                        </form>
                        <form method="POST" action="{{ route('client.favorites.store', $professional) }}">
                            @csrf
                            @method('PUT')
                            <button class="button button--secondary" type="submit"><i class="fa-solid fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button>
                        </form>
                    @endif
                @else
                    <a class="button button--primary" href="{{ route('login') }}">Se connecter pour contacter ce professionnel</a>
                    <a class="button button--secondary" href="{{ route('login') }}">Se connecter pour ajouter aux favoris</a>
                @endauth
            </aside>
        </div>
    </main>
@endsection
