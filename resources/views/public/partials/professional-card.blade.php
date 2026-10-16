@php
    $profile = $professional->user->profile;
    $displayName = $profile !== null
        ? trim($profile->first_name.' '.$profile->last_name)
        : $professional->user->name;
    $initials = collect(preg_split('/\\s+/', trim($displayName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<article class="professional-card surface-card surface-card--interactive">
    <div class="professional-card__header">
        <div class="professional-card__portrait" data-professional-portrait>
            <span class="professional-card__avatar-fallback" aria-hidden="true">{{ $initials ?: 'P' }}</span>
            <img
                class="professional-card__photo"
                data-professional-photo
                src="{{ route('public.professionals.photo', $professional) }}"
                alt="Photo de profil de {{ $displayName }}"
                loading="lazy"
                decoding="async"
            >
        </div>

        <div class="professional-card__identity">
            <div class="professional-card__badges">
                @if ($professional->verification_status->value === 'verified')
                    <span class="badge badge--success">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        Vérifié
                    </span>
                @endif

                @if ($professional->availability_status?->value === 'available')
                    <span class="badge badge--primary">
                        <span class="badge__dot" aria-hidden="true"></span>
                        Disponible
                    </span>
                @endif
            </div>

            <h3>{{ $displayName }}</h3>
            <p class="professional-card__title">{{ $professional->professional_title ?: 'Professionnel PROXIWORK' }}</p>
        </div>
    </div>

    <div class="professional-card__body">
        @if ($professional->description || $profile?->bio)
            <p class="professional-card__bio">{{ \Illuminate\Support\Str::limit($professional->description ?: $profile->bio, 150) }}</p>
        @endif

        <dl class="professional-card__meta">
            @if ($professional->city || $professional->province)
                <div>
                    <dt><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span class="sr-only">Localisation</span></dt>
                    <dd>{{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</dd>
                </div>
            @endif

            @if ($professional->rating_count > 0)
                <div class="professional-card__rating">
                    <dt><i class="fa-solid fa-star" aria-hidden="true"></i><span class="sr-only">Note</span></dt>
                    <dd>
                        <strong>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5</strong>
                        <span class="professional-card__muted">({{ $professional->rating_count }} avis)</span>
                    </dd>
                </div>
            @else
                <div>
                    <dt><i class="fa-regular fa-star" aria-hidden="true"></i><span class="sr-only">Avis</span></dt>
                    <dd class="professional-card__muted">Aucun avis pour le moment</dd>
                </div>
            @endif

            @if ($professional->years_experience !== null)
                <div>
                    <dt><i class="fa-solid fa-clock" aria-hidden="true"></i><span class="sr-only">Expérience</span></dt>
                    <dd>{{ $professional->years_experience }} an(s) d’expérience</dd>
                </div>
            @endif

            @if ($professional->starting_price !== null)
                <div>
                    <dt><i class="fa-solid fa-tag" aria-hidden="true"></i><span class="sr-only">Prix indicatif</span></dt>
                    <dd>
                        <span class="professional-card__muted">Tarif indicatif</span>
                        <strong>Dès {{ number_format((float) $professional->starting_price, 2, ',', ' ') }} {{ $professional->currency }}</strong>
                    </dd>
                </div>
            @endif

            <div>
                <dt><i class="fa-solid fa-briefcase" aria-hidden="true"></i><span class="sr-only">Services</span></dt>
                <dd>{{ $professional->published_services_count }} service{{ $professional->published_services_count > 1 ? 's' : '' }} publié{{ $professional->published_services_count > 1 ? 's' : '' }}</dd>
            </div>
        </dl>

        @if ($professional->skills->isNotEmpty())
            <div class="professional-card__skills" aria-label="Compétences principales">
                @foreach ($professional->skills->take(3) as $skill)
                    <span class="badge badge--neutral">{{ $skill->name }}</span>
                @endforeach

                @if ($professional->skills->count() > 3)
                    <span class="badge badge--neutral">+{{ $professional->skills->count() - 3 }}</span>
                @endif
            </div>
        @endif

        <div class="professional-card__actions">
            <a class="button button--primary" href="{{ route('public.professionals.show', $professional) }}">
                Voir le profil
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
            @auth
                @if (auth()->user()->hasRole('client'))
                    <form method="POST" action="{{ route('client.favorites.store', $professional) }}">
                        @csrf
                        @method('PUT')
                        <button class="button button--secondary" type="submit">
                            <i class="fa-regular fa-heart" aria-hidden="true"></i>
                            Favoris
                        </button>
                    </form>
                @endif
            @else
                <a class="button button--secondary" href="{{ route('login') }}">
                    <i class="fa-regular fa-heart" aria-hidden="true"></i>
                    Enregistrer
                </a>
            @endauth
        </div>
    </div>
</article>
