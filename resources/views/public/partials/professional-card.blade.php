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
        <div class="avatar avatar--xl professional-card__avatar" aria-hidden="true">
            {{ $initials ?: 'P' }}
        </div>

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

            <div>
                <dt><i class="fa-solid fa-star" aria-hidden="true"></i><span class="sr-only">Note</span></dt>
                <dd>
                    {{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5
                    <span class="professional-card__muted">({{ $professional->rating_count }} avis)</span>
                </dd>
            </div>

            @if ($professional->years_experience !== null)
                <div>
                    <dt><i class="fa-solid fa-clock" aria-hidden="true"></i><span class="sr-only">Expérience</span></dt>
                    <dd>{{ $professional->years_experience }} an(s) d’expérience</dd>
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
    </div>
</article>
