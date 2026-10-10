@php
    $profile = $professional->user->profile;
    $favorite = $canFavoriteProfessionals ? $professional->favorites->first() : null;
    $profileName = $profile !== null ? trim($profile->first_name.' '.$profile->last_name) : '';
    $displayName = filled($professional->business_name)
        ? $professional->business_name
        : (filled($profileName) ? $profileName : $professional->user->name);
    $profileSlug = \Illuminate\Support\Str::slug($displayName) ?: 'professionnel-'.$professional->getKey();
    $profileUrl = url('/professionals/'.$professional->getKey().'/'.$profileSlug);
    $initials = collect(preg_split('/\\s+/', trim($displayName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<article class="professional-card surface-card surface-card--interactive">
    <div class="professional-card__header">
        <a class="professional-card__avatar-link" href="{{ $profileUrl }}" aria-label="Consulter le profil de {{ $displayName }}">
            <div class="avatar avatar--xl professional-card__avatar" aria-hidden="true">
                @if ($profile?->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->avatar_path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($profile->avatar_path) }}" alt="" loading="lazy" decoding="async">
                @else
                    {{ $initials ?: 'P' }}
                @endif
            </div>
        </a>

        <div class="professional-card__identity">
            <div class="professional-card__badges">
                @if ($professional->verification_status?->value === 'verified' && $professional->verified_at !== null)
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

            <h3><a href="{{ $profileUrl }}">{{ $displayName }}</a></h3>
            <p class="professional-card__title">{{ $professional->professional_title ?: 'Professionnel PROXIWORK' }}</p>
        </div>

        @if ($canFavoriteProfessionals)
            <form
                class="professional-card__favorite-form"
                method="POST"
                action="{{ $favorite ? route('client.favorites.destroy', $favorite->getKey()) : route('client.favorites.store', $professional->getKey()) }}"
            >
                @csrf
                @if ($favorite)
                    @method('DELETE')
                @else
                    @method('PUT')
                @endif
                <button
                    class="icon-button professional-card__favorite"
                    type="submit"
                    aria-label="{{ $favorite ? 'Retirer '.$displayName.' des favoris' : 'Ajouter '.$displayName.' aux favoris' }}"
                    aria-pressed="{{ $favorite ? 'true' : 'false' }}"
                    title="{{ $favorite ? 'Retirer des favoris' : 'Ajouter aux favoris' }}"
                >
                    <i class="{{ $favorite ? 'fa-solid' : 'fa-regular' }} fa-heart" aria-hidden="true"></i>
                </button>
            </form>
        @elseif (! auth()->check())
            <a
                class="icon-button professional-card__favorite"
                href="{{ route('login', ['return_to' => '/professionals/'.$professional->getKey().'/'.$profileSlug]) }}"
                aria-label="Connectez-vous pour ajouter {{ $displayName }} aux favoris"
                title="Se connecter pour ajouter aux favoris"
            >
                <i class="fa-regular fa-heart" aria-hidden="true"></i>
            </a>
        @endif
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
                    @if (($professional->rating_count ?? 0) > 0 && $professional->rating_average !== null)
                        {{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5
                        <span class="professional-card__muted">({{ $professional->rating_count }} avis)</span>
                    @else
                        Pas encore d’avis publiés
                    @endif
                </dd>
            </div>

            @if ($professional->years_experience !== null)
                <div>
                    <dt><i class="fa-solid fa-clock" aria-hidden="true"></i><span class="sr-only">Expérience</span></dt>
                    <dd>{{ $professional->years_experience }} {{ $professional->years_experience === 1 ? 'an' : 'ans' }} d’expérience</dd>
                </div>
            @endif

            @if ($professional->starting_price !== null)
                <div>
                    <dt><i class="fa-solid fa-tag" aria-hidden="true"></i><span class="sr-only">Prix indicatif</span></dt>
                    <dd>
                        @if (filled($professional->currency))
                            Dès {{ number_format((float) $professional->starting_price, 2, ',', ' ') }} {{ $professional->currency }}
                        @else
                            Prix indicatif à confirmer
                        @endif
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
                @foreach ($professional->skills->take(5) as $skill)
                    <span class="badge badge--neutral">{{ $skill->name }}</span>
                @endforeach

                @if ($professional->skills->count() > 5)
                    <span class="badge badge--neutral">+{{ $professional->skills->count() - 5 }}</span>
                @endif
            </div>
        @endif

        <a class="button button--primary professional-card__profile-link" href="{{ $profileUrl }}">
            Voir le profil
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</article>
