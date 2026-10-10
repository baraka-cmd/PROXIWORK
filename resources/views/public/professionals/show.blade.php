@extends('layouts.public')

@section('title', e($displayName).' — Professionnel PROXIWORK')
@section('meta_description', e(\Illuminate\Support\Str::limit($professional->description ?: ($person?->bio ?: 'Découvrez les services publiés et les informations publiques de '.$displayName.'.'), 160)))

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/public/search.css')
    @endunless
    <link rel="canonical" href="{{ route('public.professionals.show', ['professionalProfile' => $professional->getKey(), 'slug' => $profileSlug]) }}">
@endpush

@section('content')
    @php
        $nameParts = preg_split('/\s+/', trim($displayName)) ?: [];
        $initials = collect($nameParts)
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'P';
    @endphp

    <section class="directory-page professional-profile-page">
        <div class="page-container">
            <nav class="service-detail-breadcrumbs" aria-label="Fil d’Ariane">
                <a href="{{ route('home') }}">Accueil</a>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                <a href="{{ route('public.search') }}">Services</a>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                <a href="{{ route('public.professionals.index') }}">Professionnels</a>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                <span aria-current="page">{{ $displayName }}</span>
            </nav>

            <header class="professional-profile-hero surface-card">
                <div class="professional-profile-hero__avatar" aria-hidden="true">
                    @if ($person?->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($person->avatar_path))
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($person->avatar_path) }}" alt="" fetchpriority="high">
                    @else
                        <span>{{ $initials }}</span>
                    @endif
                </div>

                <div class="professional-profile-hero__content">
                    <div class="professional-profile-hero__badges">
                        @if ($professional->verification_status?->value === 'verified')
                            <span class="badge badge--success">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                Professionnel vérifié
                            </span>
                        @endif
                        @if ($professional->availability_status?->value === 'available')
                            <span class="badge badge--primary">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                Disponible
                            </span>
                        @endif
                    </div>

                    <h1>{{ $displayName }}</h1>
                    <p class="professional-profile-hero__title">{{ $professional->professional_title ?: 'Professionnel PROXIWORK' }}</p>

                    <div class="professional-profile-hero__facts">
                        @if ($professional->city || $professional->province)
                            <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</span>
                        @endif
                        @if (($professional->rating_count ?? 0) > 0 && $professional->rating_average !== null)
                            <span><i class="fa-solid fa-star" aria-hidden="true"></i>{{ number_format((float) $professional->rating_average, 1, ',', ' ') }}/5 ({{ $professional->rating_count }} avis)</span>
                        @else
                            <span><i class="fa-regular fa-star" aria-hidden="true"></i>Pas encore d’avis publiés</span>
                        @endif
                        @if ($professional->years_experience !== null)
                            <span><i class="fa-solid fa-briefcase" aria-hidden="true"></i>{{ $professional->years_experience }} an(s) d’expérience</span>
                        @endif
                    </div>

                    @if ($professional->description || $person?->bio)
                        <p class="professional-profile-hero__description">{{ $professional->description ?: $person->bio }}</p>
                    @endif

                    @if ($professional->skills->isNotEmpty())
                        <div class="professional-profile-hero__skills" aria-label="Compétences publiques">
                            @foreach ($professional->skills as $skill)
                                <span class="badge badge--neutral">{{ $skill->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </header>

            <section class="professional-profile-services" aria-labelledby="professional-services-title">
                <div class="directory-results__header">
                    <div>
                        <span class="eyebrow">OFFRES PUBLIÉES</span>
                        <h2 id="professional-services-title">Services proposés</h2>
                        <p class="directory-results__summary">
                            {{ $services->total() }} service{{ $services->total() === 1 ? '' : 's' }} public{{ $services->total() === 1 ? '' : 's' }} par ce professionnel.
                        </p>
                    </div>
                </div>

                @if ($services->isEmpty())
                    <div class="state-empty surface-card">
                        <div>
                            <div class="state-empty__icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></div>
                            <h2>Aucun service publié pour le moment</h2>
                            <p>Ce professionnel ne possède actuellement aucune offre accessible au public.</p>
                            <a class="button button--ghost" href="{{ route('public.search') }}">Découvrir d’autres services</a>
                        </div>
                    </div>
                @else
                    <div class="service-grid">
                        @foreach ($services as $service)
                            @include('public.partials.service-card', ['service' => $service, 'filters' => []])
                        @endforeach
                    </div>

                    <div class="directory-pagination" aria-label="Pagination des services du professionnel">
                        {{ $services->onEachSide(1)->links() }}
                    </div>
                @endif
            </section>
        </div>
    </section>
@endsection
