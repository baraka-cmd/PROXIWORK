@extends('layouts.professional')

@section('title', 'Aperçu du service — PROXIWORK')
@section('page_heading', 'Aperçu du service')

@section('content')
    @php
        $cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first();
        $professional = $service->professionalProfile;
        $professionalName = trim(($professional->user->profile?->first_name ?? '').' '.($professional->user->profile?->last_name ?? '')) ?: $professional->user->name;
        $priceLabel = match($service->pricing_type->value) {
            'fixed' => $service->price !== null ? number_format((float) $service->price, 2, ',', ' ').' '.($service->currency ?? '') : 'Prix fixe à compléter',
            'from' => $service->price !== null ? 'À partir de '.number_format((float) $service->price, 2, ',', ' ').' '.($service->currency ?? '') : 'Prix de départ à compléter',
            'range' => $service->price_min !== null && $service->price_max !== null ? number_format((float) $service->price_min, 2, ',', ' ').' – '.number_format((float) $service->price_max, 2, ',', ' ').' '.($service->currency ?? '') : 'Fourchette à compléter',
            default => 'Sur devis',
        };
    @endphp

    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Prévisualisation privée</p>
            <h1>{{ $service->title }}</h1>
            <p class="page-lead">Cet aperçu n’est pas une publication et n’est visible que depuis votre espace professionnel.</p>
        </div>
        <div class="dashboard-page-header__actions">
            <a class="button button--ghost" href="{{ route('professional.services.edit', $service) }}">Retour à la modification</a>
        </div>
    </div>

    <div class="surface-card service-preview">
        <div class="service-preview__media">
            @if($cover)
                <img src="{{ Storage::disk('public')->url($cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}">
            @else
                <span class="service-preview__placeholder"><i class="fa-solid fa-image" aria-hidden="true"></i> Aucune image de couverture</span>
            @endif
        </div>
        <div class="service-preview__body">
            <div class="service-preview__badges">
                <span class="badge badge--neutral">{{ $service->category->name }}</span>
                <span class="badge badge--{{ $service->status->value === 'published' ? 'success' : ($service->status->value === 'archived' ? 'danger' : 'warning') }}">{{ ['draft' => 'Brouillon', 'published' => 'Publié', 'unpublished' => 'Dépublié', 'archived' => 'Archivé'][$service->status->value] ?? 'Statut inconnu' }}</span>
                <span class="badge badge--neutral">{{ $service->execution_mode->label() }}</span>
            </div>
            <h2>{{ $service->title }}</h2>
            @if($service->short_description)<p class="service-preview__summary">{{ $service->short_description }}</p>@endif
            <p class="service-preview__price">{{ $priceLabel }}</p>
            <p>{{ $service->description }}</p>

            @if($service->skills->isNotEmpty())
                <section class="public-service-detail__scope">
                    <h3>Compétences associées</h3>
                    <div class="service-card__skills">@foreach($service->skills as $skill)<span class="badge badge--neutral">{{ $skill->name }}</span>@endforeach</div>
                </section>
            @endif

            @foreach([['included_items','Ce qui est inclus'],['excluded_items','Ce qui n’est pas inclus'],['client_requirements','Ce que le client doit préparer'],['deliverables','Résultat ou livrables attendus'],['commercial_conditions','Conditions particulières']] as [$field,$label])
                @if(filled($service->{$field}))
                    <section class="public-service-detail__scope"><h3>{{ $label }}</h3><p>{{ $service->{$field} }}</p></section>
                @endif
            @endforeach

            @if($service->images->isNotEmpty())
                <section class="public-service-detail__scope">
                    <h3>Galerie</h3>
                    <div class="image-gallery">
                        @foreach($service->images as $image)
                            <div class="image-gallery__item">
                                <img src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt_text ?: $service->title }}" loading="lazy">
                                @if($image->is_cover)<span class="badge badge--success">Image principale</span>@endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="public-service-detail__scope">
                <h3>Présentation du professionnel</h3>
                <p>{{ $professionalName }}@if($professional->city), {{ $professional->city }}@endif</p>
                @if($professional->professional_title)<p>{{ $professional->professional_title }}</p>@endif
            </section>
        </div>
    </div>
@endsection
