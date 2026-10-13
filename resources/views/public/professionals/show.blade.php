@extends('layouts.public')

@section('title', $professional->professional_title ?: $professional->user->name.' — PROXIWORK')
@section('meta_description', Str::limit($professional->description ?: 'Profil professionnel PROXIWORK.', 155))

@section('content')
    @php
        $profile = $professional->user->profile;
        $displayName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: $professional->user->name;
    @endphp
    <main class="page-container public-professional-detail">
        <a class="button button--ghost" href="{{ route('public.professionals.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à l’annuaire</a>
        <header class="surface-card public-professional-detail__hero">
            @if ($profile?->avatar_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($profile->avatar_path))
                <img class="avatar avatar--xl professional-profile-avatar" src="{{ route('public.professionals.photo', $professional) }}" alt="Photo de {{ $displayName }}" loading="lazy">
            @else
                <div class="avatar avatar--xl" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</div>
            @endif
            <div><div class="professional-card__badges">@if ($professional->verification_status?->value === 'verified')
                    <span class="badge badge--success"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Vérifié</span>
                @endif@if($professional->availability_status?->value === 'available')<span class="badge badge--primary">Disponible</span>@endif</div><h1>{{ $displayName }}</h1><p>{{ $professional->professional_title ?: 'Professionnel PROXIWORK' }}</p><span>{{ collect([$professional->city, $professional->province])->filter()->join(', ') }}</span></div>
        </header>
        <div class="public-professional-detail__grid">
            <section class="surface-card public-professional-detail__main"><h2>Présentation</h2><p>{{ $professional->description ?: ($profile?->bio ?: 'Aucune présentation publiée.') }}</p><h2>Compétences</h2><div class="public-service-card__skills">@forelse($professional->skills as $skill)<span class="badge badge--neutral">{{ $skill->name }}</span>@empty<p>Aucune compétence publiée.</p>@endforelse</div><h2>Services publiés</h2><div class="public-profile-services">@forelse($professional->services as $service)<article><h3><a href="{{ route('public.services.show', $service) }}">{{ $service->title }}</a></h3><p>{{ Str::limit($service->short_description ?: $service->description, 120) }}</p></article>@empty<p>Aucun service publié.</p>@endforelse</div><h2>Avis publiés</h2><div class="public-profile-reviews">@forelse($professional->reviews as $review)<article><strong>{{ $review->rating }}/5</strong><p>{{ $review->comment }}</p><small>{{ $review->client?->name ?: 'Client PROXIWORK' }}</small></article>@empty<p>Aucun avis publié pour le moment.</p>@endforelse</div></section>
            <aside class="surface-card public-professional-detail__aside"><span class="eyebrow">RÉPUTATION</span>@if($professional->rating_count > 0)<h2>{{ number_format((float)$professional->rating_average, 1, ',', ' ') }}/5</h2><p>{{ $professional->rating_count }} avis publiés</p>@else<h2>Aucun avis</h2><p>Ce professionnel n’a pas encore reçu d’avis publié.</p>@endif
                @auth
                    @if(auth()->user()->hasRole('client'))
                        <form method="POST" action="{{ route('client.messages.start', $professional) }}" class="public-professional-contact">
                            @csrf
                            <button class="button button--primary" type="submit"><i class="fa-regular fa-comments" aria-hidden="true"></i> Contacter ce professionnel</button>
                        </form>
                        <form method="POST" action="{{ route('client.favorites.store', $professional) }}">@csrf @method('PUT')<button class="button button--secondary" type="submit"><i class="fa-solid fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button></form>
                    @endif
                @else
                    <a class="button button--primary" href="{{ route('login') }}">Se connecter pour contacter ce professionnel</a>
                    <a class="button button--secondary" href="{{ route('login') }}">Se connecter pour ajouter aux favoris</a>
                @endauth</aside>
        </div>
    </main>
@endsection