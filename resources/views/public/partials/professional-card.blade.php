@php
    $profile = $professional->user->profile;
    $displayName = $profile !== null
        ? trim($profile->first_name.' '.$profile->last_name)
        : $professional->user->name;
    $displayName = $professional->business_name ?: $displayName;
    $hasAvatar = $profile?->avatar_path
        && \Illuminate\Support\Facades\Storage::disk('local')->exists($profile->avatar_path);
    $initials = collect(preg_split('/\\s+/', trim($displayName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<article class="professional-card surface-card surface-card--interactive">
    <div class="professional-card__header">
        @if ($hasAvatar)
            <img class="avatar avatar--xl professional-card__avatar" src="{{ request()->routeIs('client.professionals.*') ? route('client.professionals.photo', $professional) : route('public.professionals.photo', $professional) }}" alt="Photo de {{ $displayName }}" loading="lazy">
        @else
            <div class="avatar avatar--xl professional-card__avatar" aria-hidden="true">
                {{ $initials ?: 'P' }}
            </div>
        @endif

        <div class="professional-card__identity">
            <div class="professional-card__badges">
                @if ($professional->verification_status->value === 'verified')
                    <span class="badge badge--success">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        Vérifié
                    </span>
                @endif

                @if ($professional->availability_status->value === 'available')
                    <span class="badge badge--primary">
                        <span class="badge__dot" aria-hidden="true"></span>
                        Disponible, déclaré
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
                <div>
                    <dt><i class="fa-solid fa-star" aria-hidden="true"></i><span class="sr-only">Note</span></dt>
                    <dd>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5 <span class="professional-card__muted">({{ $professional->rating_count }} avis)</span></dd>
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
                    <dd>{{ $professional->years_experience }} an(s) d’expérience déclarée</dd>
                </div>
            @endif

            @if ($professional->starting_price !== null)
                <div>
                    <dt><i class="fa-solid fa-tag" aria-hidden="true"></i><span class="sr-only">Prix indicatif</span></dt>
                    <dd>Dès {{ number_format((float) $professional->starting_price, 2, ',', ' ') }} {{ $professional->currency }}</dd>
                </div>
            @endif

            <div>
                <dt><i class="fa-solid fa-briefcase" aria-hidden="true"></i><span class="sr-only">Services</span></dt>
                <dd>{{ $professional->published_services_count }} service{{ $professional->published_services_count > 1 ? 's' : '' }} publié{{ $professional->published_services_count > 1 ? 's' : '' }}</dd>
            </div>
        </dl>

        @if ($professional->skills->isNotEmpty())
            <div class="professional-card__skills" aria-label="Compétences principales">
                @foreach ($professional->skills->take(5) as $skill)
                    <span class="badge badge--neutral">{{ $skill->name }}</span>
                @endforeach

                @if ($professional->skills->count() > 5)
                    <span class="badge badge--neutral">+{{ $professional->skills->count() - 5 }}</span>
                @endif
            </div>
        @endif

        <div class="professional-card__actions">
            <a class="button button--ghost" href="{{ (request()->routeIs('client.professionals.*') ? route('client.professionals.show', $professional) : route('public.professionals.show', $professional)) }}">
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                Voir le profil
            </a>
            @auth
                @if (auth()->user()->hasRole('client'))
                    @php($favorite = $professional->relationLoaded('favorites') ? $professional->favorites->first() : null)
                    @if ($favorite)
                        <form method="POST" action="{{ route('client.favorites.destroy', $favorite) }}">
                            @csrf
                            @method('DELETE')
                            <button class="button button--secondary" type="submit"><i class="fa-solid fa-heart" aria-hidden="true"></i> Retirer des favoris</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('client.favorites.store', $professional) }}">
                            @csrf
                            @method('PUT')
                            <button class="button button--secondary" type="submit"><i class="fa-regular fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button>
                        </form>
                    @endif
                @endif
            @else
                <a class="button button--secondary" href="{{ route('login', ['continue' => route('public.professionals.show', $professional)]) }}">Se connecter pour enregistrer</a>
            @endauth
        </div>
    </div>
</article>
