@extends(auth()->check() && auth()->user()->hasRole('client') ? 'layouts.client' : 'layouts.public')

@section('title', $service->title.' — PROXIWORK')
@section('meta_description', Str::limit($service->short_description ?: $service->description, 155))

@push('head')
    <link rel="canonical" href="{{ route('public.services.show', $service) }}">
@endpush

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/search.css')
    @endunless
@endpush

@section('content')
    @php
        $isClientView = auth()->check() && auth()->user()->hasRole('client');
        $favorite = $service->relationLoaded('favorites') ? $service->favorites->first() : null;
        $cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first();
        $professional = $service->professionalProfile;
        $name = trim(($professional->user->profile?->first_name ?? '').' '.($professional->user->profile?->last_name ?? '')) ?: $professional->user->name;
        $name = $professional->business_name ?: $name;
        $returnRoute = $isClientView ? route('client.services.index') : route('public.search');
        $returnUrl = url()->previous() !== url()->current() ? url()->previous() : $returnRoute;
    @endphp

    <main class="page-container public-service-detail {{ $isClientView ? 'public-service-detail--client' : '' }}">
        <a class="button button--ghost public-service-detail__back" href="{{ $returnUrl }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            {{ $isClientView ? 'Retour au catalogue des services' : 'Retour aux résultats' }}
        </a>

        <div class="public-service-detail__grid">
            <article class="surface-card public-service-detail__main">
                <div class="public-service-detail__media">
                    @if ($cover)
                        <img src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}">
                    @else
                        <span aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
                    @endif
                </div>

                <div class="public-service-detail__content">
                    <p class="eyebrow">{{ $service->category->name }}</p>
                    <h1>{{ $service->title }}</h1>
                    <p class="public-service-detail__description">{{ $service->description }}</p>

                    @if ($service->skills->isNotEmpty())
                        <section class="public-service-detail__section" aria-labelledby="service-skills-title">
                            <h2 id="service-skills-title">Compétences mobilisées</h2>
                            <div class="public-service-card__skills" aria-label="Compétences nécessaires">
                                @foreach ($service->skills as $skill)
                                    <span class="badge badge--neutral">{{ $skill->name }}</span>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @foreach ([['included_items', 'Ce qui est inclus'], ['excluded_items', 'Ce qui n’est pas inclus'], ['client_requirements', 'Ce que vous devez préparer'], ['deliverables', 'Résultats et livrables attendus'], ['commercial_conditions', 'Conditions particulières']] as [$field, $label])
                        @if (filled($service->{$field}))
                            <section class="public-service-detail__scope">
                                <h2>{{ $label }}</h2>
                                <p>{{ $service->{$field} }}</p>
                            </section>
                        @endif
                    @endforeach

                    @if ($service->realizations->where('status', 'approved')->where('participation_confirmed', true)->isNotEmpty())
                        <section class="public-service-detail__scope" aria-labelledby="service-realizations-title">
                            <h2 id="service-realizations-title">Réalisations approuvées</h2>
                            <p>Découvrez des exemples de travaux partagés par le professionnel et examinés par PROXIWORK. Ils illustrent son expérience, sans garantir un résultat identique pour une future prestation.</p>
                            <div class="realization-public-grid">
                                @foreach ($service->realizations->where('status', 'approved')->where('participation_confirmed', true) as $realization)
                                    <figure class="realization-public-card">
                                        <img src="{{ route('public.service-realizations.image', $realization) }}" alt="{{ $realization->caption }}" loading="lazy">
                                        <figcaption>{{ $realization->caption }}</figcaption>
                                        @if ($realization->completed_at)
                                            <small>Réalisée le {{ $realization->completed_at->format('d/m/Y') }}</small>
                                        @endif
                                    </figure>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            </article>

            <aside class="surface-card public-service-detail__aside" aria-label="Tarif et demande de prestation">
                <p class="eyebrow">INFORMATIONS SUR LA PRESTATION</p>
                <span class="badge badge--neutral public-service-detail__mode">{{ $service->execution_mode->label() }}</span>
                <div class="public-service-detail__price">
                    <span>Tarif indicatif</span>
                    <h2>
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
                    </h2>
                </div>
                <p class="public-service-detail__provider">
                    Proposé par <strong>{{ $name }}</strong>
                    @if ($professional->city)
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $professional->city }}</span>
                    @endif
                </p>

                <div class="public-service-detail__actions">
                    @auth
                        @if (auth()->user()->hasRole('client'))
                            <a class="button button--primary" href="{{ route('client.requests.create', ['service' => $service->id]) }}">
                                <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                                Demander ce service
                            </a>
                            @if ($favorite)
                                <form method="POST" action="{{ route('client.favorites.destroy', $favorite) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button--secondary" type="submit"><i class="fa-solid fa-heart" aria-hidden="true"></i> Retirer des favoris</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('client.favorites.services.store', $service) }}">
                                    @csrf
                                    <button class="button button--secondary" type="submit"><i class="fa-regular fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button>
                                </form>
                            @endif
                        @else
                            <p>Cette action est réservée à l’espace client.</p>
                        @endif
                    @else
                        <a class="button button--primary" href="{{ route('client.requests.create', ['service' => $service->id]) }}">
                            <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
                            Demander ce service
                        </a>
                        <a class="button button--secondary" href="{{ route('login', ['continue' => route('public.services.show', $service)]) }}">
                            <i class="fa-regular fa-heart" aria-hidden="true"></i>
                            Se connecter pour enregistrer
                        </a>
                    @endauth
                </div>
            </aside>
        </div>
    </main>
@endsection
