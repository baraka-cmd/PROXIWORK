@extends('layouts.professional')

@section('title', 'Mes services — PROXIWORK')
@section('page_heading', 'Mes services')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/professional/services.css')
    @endunless
@endpush

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Catalogue professionnel</p>
            <h1>Mes services</h1>
            <p class="page-lead">Créez, organisez et publiez les prestations proposées aux clients.</p>
        </div>
        <div class="dashboard-page-header__actions">
            <a class="button button--primary" href="{{ route('professional.services.create') }}">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter un service
            </a>
        </div>
    </div>

    <section class="service-summary-grid" aria-label="Résumé du catalogue">
        @foreach([
            ['label' => 'Tous les services', 'value' => $serviceCounts['all'], 'icon' => 'fa-layer-group'],
            ['label' => 'Publiés', 'value' => $serviceCounts['published'], 'icon' => 'fa-eye'],
            ['label' => 'Brouillons', 'value' => $serviceCounts['draft'], 'icon' => 'fa-file-pen'],
            ['label' => 'Dépubliés', 'value' => $serviceCounts['unpublished'], 'icon' => 'fa-eye-slash'],
            ['label' => 'Archivés', 'value' => $serviceCounts['archived'], 'icon' => 'fa-box-archive'],
            ['label' => 'Preuves en attente', 'value' => $serviceCounts['pending_realizations'], 'icon' => 'fa-hourglass-half'],
        ] as $summary)
            <article class="service-summary-card surface-card">
                <span class="service-summary-card__icon"><i class="fa-solid {{ $summary['icon'] }}" aria-hidden="true"></i></span>
                <div><p>{{ $summary['label'] }}</p><strong>{{ $summary['value'] }}</strong></div>
            </article>
        @endforeach
    </section>

    <form class="service-filters surface-card" method="GET" action="{{ route('professional.services.index') }}">
        <div class="service-filter-field">
            <label class="form-label" for="service-search">Recherche</label>
            <input class="form-control" id="service-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Titre ou description..." maxlength="120">
        </div>
        <div class="service-filter-field">
            <label class="form-label" for="service-status">Statut</label>
            <select class="form-control form-select" id="service-status" name="status">
                <option value="">Tous les statuts</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $statusLabels[$status->value] }}</option>
                @endforeach
            </select>
        </div>
        <div class="service-filter-field">
            <label class="form-label" for="service-category">Catégorie</label>
            <select class="form-control form-select" id="service-category" name="category_id">
                <option value="">Toutes les catégories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="service-filter-actions">
            <button class="button button--primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrer</button>
            <a class="button button--ghost" href="{{ route('professional.services.index') }}">Réinitialiser</a>
        </div>
    </form>

    @if($services->isNotEmpty())
        <div class="service-grid">
            @foreach($services as $service)
                @php
                    $statusClass = match($service->status->value) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'unpublished' => 'neutral',
                        'archived' => 'danger',
                        default => 'neutral',
                    };
                    $cover = $service->images->firstWhere('is_cover', true) ?? $service->images->first();
                    $priceLabel = match($service->pricing_type->value) {
                        'fixed' => $service->price !== null ? number_format((float) $service->price, 2, ',', ' ').' '.($service->currency ?? '') : 'Prix fixe à compléter',
                        'from' => $service->price !== null ? 'À partir de '.number_format((float) $service->price, 2, ',', ' ').' '.($service->currency ?? '') : 'Prix de départ à compléter',
                        'range' => $service->price_min !== null && $service->price_max !== null ? number_format((float) $service->price_min, 2, ',', ' ').' – '.number_format((float) $service->price_max, 2, ',', ' ').' '.($service->currency ?? '') : 'Fourchette à compléter',
                        default => 'Sur devis',
                    };
                @endphp
                <article class="service-card surface-card surface-card--interactive">
                    <div class="service-card__media">
                        @if($cover)
                            <img src="{{ Storage::disk('public')->url($cover->path) }}" alt="{{ $cover->alt_text ?: $service->title }}" loading="lazy">
                        @else
                            <span><i class="fa-solid fa-image" aria-hidden="true"></i><small>Pas de couverture</small></span>
                        @endif
                        <span class="badge badge--{{ $statusClass }}">{{ $statusLabels[$service->status->value] }}</span>
                    </div>
                    <div class="service-card__body">
                        <p class="service-card__category">{{ $service->category?->name ?: 'Catégorie non renseignée' }}</p>
                        <h2>{{ $service->title }}</h2>
                        <p>{{ $service->short_description ?: $service->description }}</p>
                        <div class="service-card__price">{{ $priceLabel }}</div>
                        <div class="service-card__meta">
                            <span><i class="fa-solid fa-briefcase" aria-hidden="true"></i> {{ $service->service_requests_count }} demande(s)</span>
                            @if($service->pending_realizations_count > 0)<span><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> {{ $service->pending_realizations_count }} preuve(s) en attente</span>@endif
                            @if($service->estimated_duration_minutes)
                                <span><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $service->estimated_duration_minutes }} min</span>
                            @endif
                        </div>
                        @if($service->skills->isNotEmpty())
                            <div class="service-card__skills">
                                @foreach($service->skills->take(4) as $skill)
                                    <span class="badge badge--neutral">{{ $skill->name }}</span>
                                @endforeach
                            </div>
                        @endif
                        <p class="service-card__updated">Dernière modification : {{ $service->updated_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="service-card__footer">
                        <a class="button button--ghost button--sm" href="{{ route('professional.services.preview', $service) }}">Aperçu</a>
                        <a class="button button--ghost button--sm" href="{{ route('professional.requests.index', ['service_id' => $service->id]) }}">Demandes</a>
                        <a class="button button--ghost button--sm" href="{{ route('professional.services.edit', $service) }}">Modifier</a>
                        @if($service->status->value === 'published')
                            <form method="POST" action="{{ route('professional.services.unpublish', $service) }}" data-action-form>
                                @csrf
                                <button class="button button--ghost button--sm" type="submit">Dépublier</button>
                            </form>
                        @elseif($service->status->value !== 'archived')
                            <form method="POST" action="{{ route('professional.services.publish', $service) }}" data-action-form>
                                @csrf
                                <button class="button button--primary button--sm" type="submit">Publier</button>
                            </form>
                        @endif
                        @if($service->status->value !== 'archived')
                            <form method="POST" action="{{ route('professional.services.archive', $service) }}" data-action-form data-confirm-archive>
                                @csrf
                                @method('DELETE')
                                <button class="button button--danger button--sm" type="submit">Archiver</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <div class="professional-pagination">{{ $services->links() }}</div>
    @else
        <section class="surface-card state-empty">
            <div>
                <span class="state-empty__icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                <h2>Aucun service trouvé</h2>
                <p>Modifiez vos filtres ou créez votre première prestation.</p>
                <a class="button button--primary" href="{{ route('professional.services.create') }}">Créer un service</a>
            </div>
        </section>
    @endif
@endsection
