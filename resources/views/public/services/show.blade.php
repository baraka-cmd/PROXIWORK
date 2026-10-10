@extends('layouts.public')

@section('title', e($service->title).' — PROXIWORK')
@section('meta_description', e(\Illuminate\Support\Str::limit($service->short_description ?: $service->description, 160)))

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/search.css')
    @endunless
    <link rel="canonical" href="{{ route('public.services.show', ['service' => $service->slug]) }}">
@endpush

@section('content')
    @php
        $professional = $service->professionalProfile;
        $person = $professional?->user?->profile;
        $personName = $person !== null ? trim($person->first_name.' '.$person->last_name) : '';
        $displayName = filled($professional?->business_name)
            ? $professional->business_name
            : (filled($personName) ? $personName : ($professional?->user?->name ?? 'Professionnel PROXIWORK'));
        $professionalUrl = $professional
            ? route('public.professionals.show', [
                'professionalProfile' => $professional->getKey(),
                'slug' => IlluminateSupportStr::slug($displayName) ?: 'professionnel-'.$professional->getKey(),
            ])
            : null;
        $currency = $service->currency ? ' '.$service->currency : ' (devise à confirmer)';
    $billingUnitLabel = match ($service->billing_unit) {
        'package' => 'prestation',
        'hour' => 'heure',
        'day' => 'jour',
        'project' => 'projet',
        default => $service->billing_unit ? str_replace('_', ' ', $service->billing_unit) : null,
    };
        $priceLabel = match ($service->pricing_type->value) {
            'fixed' => $service->price !== null
                ? number_format((float) $service->price, 2, ',', ' ').$currency
                : 'Sur devis',
            'from' => $service->price !== null
                ? 'À partir de '.number_format((float) $service->price, 2, ',', ' ').$currency
                : 'Sur devis',
            'range' => $service->price_min !== null && $service->price_max !== null
                ? number_format((float) $service->price_min, 2, ',', ' ').' – '.number_format((float) $service->price_max, 2, ',', ' ').$currency
                : ($service->price_min !== null
                    ? 'À partir de '.number_format((float) $service->price_min, 2, ',', ' ').$currency
                    : ($service->price_max !== null
                        ? 'Jusqu’à '.number_format((float) $service->price_max, 2, ',', ' ').$currency
                        : 'Sur devis')),
            default => 'Sur devis',
        };
        if (filled($service->billing_unit) && $priceLabel !== 'Sur devis') {
            $priceLabel .= ' / '.$billingUnitLabel;
        }
    @endphp

    <main class="service-detail-page">
        <div class="page-container">
            <nav class="service-detail-breadcrumbs" aria-label="Fil d’Ariane">
                <a href="{{ route('home') }}">Accueil</a>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                <a href="{{ route('public.search') }}">Services</a>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                @if ($service->category)
                    <a href="{{ route('public.search', ['category' => $service->category->slug]) }}">{{ $service->category->name }}</a>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                @endif
                <span aria-current="page">{{ $service->title }}</span>
            </nav>

            <div class="service-detail-layout">
                <article class="service-detail-main">
                    <header class="service-detail-heading">
                        @if ($service->category)
                            <a class="service-card__category" href="{{ route('public.search', ['category' => $service->category->slug]) }}">{{ $service->category->name }}</a>
                        @endif
                        <h1>{{ $service->title }}</h1>
                        @if ($service->short_description)
                            <p>{{ $service->short_description }}</p>
                        @endif
                        <div class="service-detail-trust">
                            @if ($professional?->verification_status?->value === 'verified')
                                <span class="badge badge--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Professionnel vérifié</span>
                            @endif
                            @if (($professional?->rating_count ?? 0) > 0 && $professional?->rating_average !== null)
                                <span class="service-detail-rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5 ({{ $professional->rating_count }} avis)</span>
                            @else
                                <span class="service-detail-rating"><i class="fa-regular fa-star" aria-hidden="true"></i> Pas encore d’avis publiés</span>
                            @endif
                        </div>
                    </header>

                    <div class="service-detail-gallery">
                        @if ($coverImage && \Illuminate\Support\Facades\Storage::disk('public')->exists($coverImage->path))
                            <img class="service-detail-cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->path) }}" alt="{{ $coverImage->alt_text ?: $service->title }}" fetchpriority="high">
                        @else
                            <div class="service-detail-cover service-detail-cover--placeholder" aria-label="Aucune image disponible">
                                <i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                                <span>Image du service non disponible</span>
                            </div>
                        @endif

                        @if ($service->images->count() > 1)
                            <div class="service-detail-thumbnails" aria-label="Autres images du service">
                                @foreach ($service->images->where('id', '!=', $coverImage?->id)->filter(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->exists($image->path)) as $image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt_text ?: $service->title }}" loading="lazy" decoding="async">
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <section class="service-detail-section" aria-labelledby="service-description-title">
                        <h2 id="service-description-title">À propos de ce service</h2>
                        <p class="service-detail-description">{{ $service->description ?: $service->short_description ?: 'Le professionnel pourra préciser les détails de cette prestation lors de votre demande.' }}</p>
                    </section>

                    @if ($service->skills->isNotEmpty())
                        <section class="service-detail-section" aria-labelledby="service-skills-title">
                            <h2 id="service-skills-title">Compétences associées</h2>
                            <div class="service-detail-tags">
                                @foreach ($service->skills as $skill)
                                    <a class="badge badge--neutral" href="{{ route('public.search', ['skills' => [$skill->slug]]) }}">{{ $skill->name }}</a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="service-detail-section" aria-labelledby="service-professional-title">
                        <h2 id="service-professional-title">Le professionnel</h2>
                        <div class="service-detail-professional">
                            <span class="service-card__professional-avatar service-card__professional-avatar--large" aria-hidden="true">
                                @if ($person?->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($person->avatar_path))
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person->avatar_path) }}" alt="" loading="lazy" decoding="async">
                                @else
                                    {{ collect(preg_split('/\s+/', trim($displayName)) ?: [])->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'P' }}
                                @endif
                            </span>
                            <div>
                                <h3>@if ($professionalUrl)<a href="{{ $professionalUrl }}">{{ $displayName }}</a>@else{{ $displayName }}@endif</h3>
                                <p>{{ $professional?->professional_title ?: 'Prestataire PROXIWORK' }}</p>
                                @if ($professional?->description)
                                    <p>{{ \Illuminate\Support\Str::limit($professional->description, 240) }}</p>
                                @endif
                                @if ($professional?->years_experience !== null)
                                    <p>{{ $professional->years_experience }} an(s) d’expérience</p>
                                @endif
                                @if ($professional?->verification_status?->value === 'verified')
                                    <span class="badge badge--success"><i class="fa-solid fa-shield-check" aria-hidden="true"></i> Vérification accordée</span>
                                @endif
                            </div>
                        </div>
                    </section>
                </article>

                <aside class="service-detail-aside" aria-label="Résumé du service">
                    <div class="service-detail-price-panel surface-card">
                        <span class="eyebrow">TARIFICATION</span>
                        <p class="service-detail-price">{{ $priceLabel }}</p>
                        <p class="service-detail-price-note">
                            @switch($service->pricing_type->value)
                                @case('fixed') Tarif fixe indiqué par le professionnel. @break
                                @case('from') Tarif de départ, le montant final peut varier selon le besoin. @break
                                @case('range') Fourchette indicative proposée par le professionnel. @break
                                @default Le prix sera précisé dans le devis.
                            @endswitch
                        </p>
                        @if (auth()->guest() || auth()->user()->hasRole('client'))
                            <a class="button button--primary button--lg service-detail-cta" href="{{ route('client.requests.create', ['service' => $service->id]) }}">
                                <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i> Demander un devis
                            </a>
                            <p class="service-detail-private-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> La demande est réservée aux comptes clients. Si vous n’êtes pas connecté, vous serez invité à vous connecter.</p>
                        @else
                            <p class="service-detail-private-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Cette action est disponible dans l’espace client.</p>
                        @endif
                    </div>

                    <div class="service-detail-facts surface-card">
                        <h2>Informations utiles</h2>
                        <dl>
                            <div><dt>Catégorie</dt><dd>{{ $service->category?->name ?? 'Non renseignée' }}</dd></div>
                            <div><dt>Zone d’intervention</dt><dd>{{ $service->service_area ?: (collect([$professional?->city, $professional?->province])->filter()->join(', ') ?: 'À confirmer avec le professionnel') }}</dd></div>
                            <div><dt>Durée estimée</dt><dd>{{ $service->estimated_duration_minutes ? $service->estimated_duration_minutes.' min' : 'À définir' }}</dd></div>
                            <div><dt>Publié le</dt><dd>{{ $service->published_at?->format('d/m/Y') ?? 'Non renseigné' }}</dd></div>
                        </dl>
                    </div>
                    <a class="service-detail-back" href="{{ route('public.search', request()->query()) }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour aux résultats</a>
                </aside>
            </div>
        </div>
    </main>
@endsection
