@php
    $professional = $service->professionalProfile;
    $person = $professional?->user?->profile;

    $personName = $person !== null ? trim($person->first_name.' '.$person->last_name) : '';
    $displayName = filled($professional?->business_name)
        ? $professional->business_name
        : (filled($personName) ? $personName : ($professional?->user?->name ?? 'Professionnel PROXIWORK'));

    $detailUrl = route('public.services.show', array_merge(
        ['service' => $service->slug],
        collect($filters ?? [])->except('page')->all()
    ));

    $professionalSlug = \Illuminate\Support\Str::slug($displayName);
    if ($professionalSlug === '') {
        $professionalSlug = 'professionnel-'.$professional?->getKey();
    }
    $professionalUrl = $professional
        ? route('public.professionals.show', [
            'professionalProfile' => $professional->getKey(),
            'slug' => $professionalSlug,
        ])
        : null;

    $categoryUrl = $service->category
        ? route('public.search', array_merge(
            collect($filters ?? [])->except(['page', 'category'])->all(),
            ['category' => $service->category->slug]
        ))
        : null;

    $serviceAreaLabel = $service->service_area
        ?: collect([$professional?->city, $professional?->province])->filter()->join(', ');
    if ($serviceAreaLabel === '') {
        $serviceAreaLabel = 'Zone à confirmer';
    }

    $nameParts = preg_split('/\s+/', trim($displayName)) ?: [];
    $initials = collect($nameParts)
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    if ($initials === '') {
        $initials = 'P';
    }

    $cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first();
    $coverExists = $cover !== null
        && filled($cover->path)
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($cover->path);

    $pricingType = $service->pricing_type?->value ?? 'quote';
    $priceLabel = 'Sur devis';
    if (in_array($pricingType, ['fixed', 'from'], true) && $service->price !== null) {
        $amount = number_format((float) $service->price, 2, ',', ' ');
        $priceLabel = $pricingType === 'from' ? 'À partir de '.$amount : $amount;
    } elseif ($pricingType === 'range') {
        if ($service->price_min !== null && $service->price_max !== null) {
            $priceLabel = number_format((float) $service->price_min, 2, ',', ' ')
                .' – '.number_format((float) $service->price_max, 2, ',', ' ');
        } elseif ($service->price_min !== null) {
            $priceLabel = 'À partir de '.number_format((float) $service->price_min, 2, ',', ' ');
        } elseif ($service->price_max !== null) {
            $priceLabel = 'Jusqu’à '.number_format((float) $service->price_max, 2, ',', ' ');
        }
    }

    if ($priceLabel !== 'Sur devis') {
        $priceLabel .= filled($service->currency) ? ' '.$service->currency : ' (devise à confirmer)';

        $billingUnitLabel = match ($service->billing_unit) {
            'package' => 'prestation',
            'hour' => 'heure',
            'day' => 'jour',
            'project' => 'projet',
            default => filled($service->billing_unit)
                ? str_replace('_', ' ', $service->billing_unit)
                : null,
        };
        $priceLabel .= $billingUnitLabel !== null
            ? ' / '.$billingUnitLabel
            : ' — unité à confirmer';
    }

    $pricingTypeLabel = match ($pricingType) {
        'fixed' => 'Tarif fixe',
        'from' => 'Tarif indicatif',
        'range' => 'Fourchette indicative',
        default => 'Sur devis',
    };
@endphp

<article class="service-card surface-card surface-card--interactive">
    <a class="service-card__media" href="{{ $detailUrl }}" aria-label="Consulter le service {{ $service->title }}">
        @if ($coverExists)
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($cover->path) }}"
                alt="{{ $cover->alt_text ?: $service->title }}"
                loading="lazy"
                decoding="async"
            >
        @else
            <span class="service-card__placeholder" aria-hidden="true">
                <i class="fa-solid fa-briefcase"></i>
            </span>
        @endif

        @if ($professional?->verification_status?->value === 'verified')
            <span class="service-card__verified">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                Professionnel vérifié
            </span>
        @endif
    </a>

    <div class="service-card__content">
        @if ($service->category && $categoryUrl)
            <a class="service-card__category" href="{{ $categoryUrl }}">{{ $service->category->name }}</a>
        @endif

        @if ($professional?->availability_status?->value === 'available')
            <span class="service-card__availability">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                Disponible
            </span>
        @endif

        <h3><a href="{{ $detailUrl }}">{{ $service->title }}</a></h3>

        @if ($service->short_description)
            <p class="service-card__description">{{ \Illuminate\Support\Str::limit($service->short_description, 145) }}</p>
        @endif

        <div class="service-card__professional">
            @if ($professionalUrl)
                <a
                    class="service-card__professional-avatar-link"
                    href="{{ $professionalUrl }}"
                    aria-label="Consulter le profil de {{ $displayName }}"
                >
            @endif

            <span class="service-card__professional-avatar" aria-hidden="true">
                @if ($person?->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($person->avatar_path))
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person->avatar_path) }}"
                        alt=""
                        loading="lazy"
                        decoding="async"
                    >
                @else
                    {{ $initials }}
                @endif
            </span>

            @if ($professionalUrl)
                </a>
            @endif

            <span>
                <strong>
                    @if ($professionalUrl)
                        <a href="{{ $professionalUrl }}">{{ $displayName }}</a>
                    @else
                        {{ $displayName }}
                    @endif
                </strong>
                <small>{{ $professional?->professional_title ?: 'Prestataire PROXIWORK' }}</small>
            </span>
        </div>

        <div class="service-card__facts">
            <span>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                {{ $serviceAreaLabel }}
            </span>

            @if (($professional?->rating_count ?? 0) > 0 && $professional?->rating_average !== null)
                <span>
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    {{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5
                    <small>({{ $professional->rating_count }} avis)</small>
                </span>
            @else
                <span><i class="fa-regular fa-star" aria-hidden="true"></i> Nouveau service</span>
            @endif
        </div>

        <div class="service-card__footer">
            <div class="service-card__price">
                <small>{{ $pricingTypeLabel }}</small>
                <strong>{{ $priceLabel }}</strong>
            </div>
            <a class="button button--primary" href="{{ $detailUrl }}">Voir le service</a>
        </div>
    </div>
</article>
