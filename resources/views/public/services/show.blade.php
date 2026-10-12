@extends('layouts.public')

@section('title', $service->title.' — PROXIWORK')
@section('meta_description', Str::limit($service->short_description ?: $service->description, 155))

@section('content')
    @php($cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first())
    @php($professional = $service->professionalProfile)
    @php($name = trim(($professional->user->profile?->first_name ?? '').' '.($professional->user->profile?->last_name ?? '')) ?: $professional->user->name)
    <main class="page-container public-service-detail">
        <a class="button button--ghost" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('public.search') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour aux résultats</a>
        <div class="public-service-detail__grid">
            <section class="surface-card public-service-detail__main">
                <div class="public-service-detail__media">@if($cover)<img src="{{ asset('storage/'.$cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}">@else<span><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>@endif</div>
                <p class="eyebrow">{{ $service->category->name }}</p><h1>{{ $service->title }}</h1><p>{{ $service->description }}</p>
                @if($service->skills->isNotEmpty())<div class="public-service-card__skills">@foreach($service->skills as $skill)<span class="badge badge--neutral">{{ $skill->name }}</span>@endforeach</div>@endif
            </section>
            <aside class="surface-card public-service-detail__aside"><span class="eyebrow">PRESTATION</span><h2>@if($service->pricing_type->value === 'quote') Sur devis @elseif($service->pricing_type->value === 'range') {{ number_format((float)$service->price_min, 2, ',', ' ') }}–{{ number_format((float)$service->price_max, 2, ',', ' ') }} {{ $service->currency }} @else {{ number_format((float)$service->price, 2, ',', ' ') }} {{ $service->currency }} @endif</h2><p>Proposé par <strong>{{ $name }}</strong>@if($professional->city), {{ $professional->city }}@endif</p><a class="button button--primary" href="{{ route('client.requests.create', ['service' => $service->id]) }}">Demander ce service</a>
                @auth
                    @if (auth()->user()->hasRole('client'))
                        <form method="POST" action="{{ route('client.favorites.services.store', $service) }}">
                            @csrf
                            <button class="button button--secondary" type="submit"><i class="fa-solid fa-heart" aria-hidden="true"></i> Ajouter aux favoris</button>
                        </form>
                    @endif
                @else
                    <a class="button button--secondary" href="{{ route('login') }}">Se connecter pour enregistrer</a>
                @endauth</aside>
        </div>
    </main>
@endsection