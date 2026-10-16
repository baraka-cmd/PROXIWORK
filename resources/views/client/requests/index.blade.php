@extends('layouts.client')

@section('title', 'Mes demandes')

@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
    @vite('resources/css/pages/client/service-requests.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/client/favorites-requests-quotes.js')
@endpush

@section('content')
@php
    $statusFilters = [
        '' => 'Toutes',
        'draft' => 'Brouillons',
        'requested' => 'En attente',
        'quoted' => 'Réponses à consulter',
        'accepted' => 'Acceptées',
        'rejected' => 'Refusées',
        'cancelled' => 'Annulées',
        'closed' => 'Clôturées',
    ];
    $queryWithoutStatus = collect($filters)->except('status')->all();
    $activeFilterCount = collect($filters)->filter(fn ($value, $key) => $value !== null && $value !== '' && ! ($key === 'sort' && $value === 'newest'))->count();
@endphp

<div class="client-module client-requests-page">
    <div class="client-module__header client-requests-header">
        <div>
            <p class="client-module__eyebrow">SUIVI DES PRESTATIONS</p>
            <h1>Mes demandes</h1>
            <p>Retrouvez vos échanges, les réponses des professionnels et la prochaine étape de chaque demande.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('client.services.index') }}">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            Trouver un service
        </a>
    </div>

    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <section class="request-stat-grid" aria-label="Résumé de mes demandes">
        <a class="request-stat-card {{ $selectedStatus === '' ? 'is-selected' : '' }}" href="{{ route('client.requests.index', $queryWithoutStatus) }}">
            <span class="request-stat-card__label">Toutes les demandes</span>
            <strong>{{ $stats['total'] }}</strong>
            <span class="request-stat-card__hint">Historique complet</span>
        </a>
        <a class="request-stat-card {{ $selectedStatus === 'requested' ? 'is-selected' : '' }}" href="{{ route('client.requests.index', array_merge($queryWithoutStatus, ['status' => 'requested'])) }}">
            <span class="request-stat-card__label">En attente du professionnel</span>
            <strong>{{ $stats['awaiting_professional'] }}</strong>
            <span class="request-stat-card__hint">Une réponse est attendue</span>
        </a>
        <a class="request-stat-card {{ $selectedStatus === 'quoted' ? 'is-selected' : '' }}" href="{{ route('client.requests.index', array_merge($queryWithoutStatus, ['status' => 'quoted'])) }}">
            <span class="request-stat-card__label">Réponses à consulter</span>
            <strong>{{ $stats['awaiting_client'] }}</strong>
            <span class="request-stat-card__hint">Vérifiez le devis reçu</span>
        </a>
        <a class="request-stat-card {{ $selectedStatus === 'closed' ? 'is-selected' : '' }}" href="{{ route('client.requests.index', array_merge($queryWithoutStatus, ['status' => 'closed'])) }}">
            <span class="request-stat-card__label">Demandes clôturées</span>
            <strong>{{ $stats['closed'] }}</strong>
            <span class="request-stat-card__hint">Refusées, annulées ou acceptées</span>
        </a>
    </section>

    <nav class="request-status-filters" aria-label="Filtrer par statut">
        @foreach($statusFilters as $value => $label)
            @php
                $statusQuery = $value === ''
                    ? $queryWithoutStatus
                    : array_merge($queryWithoutStatus, ['status' => $value]);
            @endphp
            <a class="request-status-filter {{ $selectedStatus === $value ? 'is-active' : '' }}" @if($selectedStatus === $value) aria-current="page" @endif
               href="{{ route('client.requests.index', $statusQuery) }}"
               @if($selectedStatus === $value) aria-current="page" @endif>
                {{ $label }}
                @if($value === '') <span>{{ $stats['total'] }}</span>
                @elseif($value === 'closed') <span>{{ $stats['closed'] }}</span>
                @elseif($value === 'requested') <span>{{ $stats['requested'] }}</span>
                @elseif($value === 'quoted') <span>{{ $stats['quoted'] }}</span>
                @elseif(isset($stats[$value])) <span>{{ $stats[$value] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <section class="request-filter-panel" aria-label="Recherche et filtres">
        <form method="GET" action="{{ route('client.requests.index') }}" class="request-filter-form">
            <div class="request-filter-field request-filter-field--search">
                <label for="request-search">Rechercher</label>
                <div class="request-search-input">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input id="request-search" name="search" type="search" maxlength="120"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Titre, service, professionnel ou référence">
                </div>
            </div>
            <div class="request-filter-field">
                <label for="request-professional">Professionnel</label>
                <select id="request-professional" name="professional_id">
                    <option value="">Tous les professionnels</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected((string) ($filters['professional_id'] ?? '') === (string) $professional->id)>
                            {{ $professional->user?->name ?? $professional->professional_title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="request-filter-field">
                <label for="request-category">Catégorie</label>
                <select id="request-category" name="category_id">
                    <option value="">Toutes les catégories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="request-filter-field">
                <label for="request-from">Du</label>
                <input id="request-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="request-filter-field">
                <label for="request-to">Au</label>
                <input id="request-to" name="to" type="date" min="{{ $filters['from'] ?? '' }}" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="request-filter-field">
                <label for="request-sort">Trier par</label>
                <select id="request-sort" name="sort">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Plus récentes</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Plus anciennes</option>
                </select>
            </div>
            @if($selectedStatus !== '')
                <input type="hidden" name="status" value="{{ $selectedStatus }}">
            @endif
            <div class="request-filter-actions">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Appliquer</button>
                <a class="btn btn-secondary" href="{{ route('client.requests.index') }}">Réinitialiser</a>
            </div>
        </form>

        @if($activeFilterCount > 0)
            <div class="request-active-filters" aria-label="Filtres actifs">
                <span>Filtres actifs :</span>
                @if(!empty($filters['search']))
                    <a href="{{ route('client.requests.index', collect($filters)->except('search')->all()) }}">Recherche : {{ $filters['search'] }} <span aria-hidden="true">×</span></a>
                @endif
                @if(!empty($filters['professional_id']))
                    <a href="{{ route('client.requests.index', collect($filters)->except('professional_id')->all()) }}">Professionnel <span aria-hidden="true">×</span></a>
                @endif
                @if(!empty($filters['category_id']))
                    <a href="{{ route('client.requests.index', collect($filters)->except('category_id')->all()) }}">Catégorie <span aria-hidden="true">×</span></a>
                @endif
                @if(!empty($filters['from']))
                    <a href="{{ route('client.requests.index', collect($filters)->except('from')->all()) }}">Depuis le {{ $filters['from'] }} <span aria-hidden="true">×</span></a>
                @endif
                @if(!empty($filters['to']))
                    <a href="{{ route('client.requests.index', collect($filters)->except('to')->all()) }}">Jusqu’au {{ $filters['to'] }} <span aria-hidden="true">×</span></a>
                @endif
                @if(!empty($filters['sort']) && $filters['sort'] !== 'newest')
                    <a href="{{ route('client.requests.index', collect($filters)->except('sort')->all()) }}">Tri personnalisé <span aria-hidden="true">×</span></a>
                @endif
                @if($selectedStatus !== '')
                    <a href="{{ route('client.requests.index', $queryWithoutStatus) }}">Statut : {{ $statusFilters[$selectedStatus] ?? $selectedStatus }} <span aria-hidden="true">×</span></a>
                @endif
            </div>
        @endif
    </section>

    <div class="request-list-heading">
        <div>
            <h2>{{ $selectedStatus === '' ? 'Votre historique' : ($statusFilters[$selectedStatus] ?? 'Demandes filtrées') }}</h2>
            <p>{{ $requests->total() }} résultat{{ $requests->total() > 1 ? 's' : '' }} · Les informations sont limitées à votre compte.</p>
        </div>
    </div>

    @if($requests->isEmpty())
        <div class="request-empty-state">
            <span class="request-empty-state__icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
            <h2>{{ $activeFilterCount > 0 ? 'Aucune demande ne correspond à ces critères' : 'Aucune demande pour le moment' }}</h2>
            <p>{{ $activeFilterCount > 0 ? 'Essayez de modifier vos filtres ou explorez les services disponibles pour créer une nouvelle demande.' : 'Vos demandes apparaîtront ici dès que vous commencerez une démarche auprès d’un professionnel.' }}</p>
            <div>
                @if($activeFilterCount > 0)
                    <a class="btn btn-secondary" href="{{ route('client.requests.index') }}">Effacer les filtres</a>
                @endif
                <a class="btn btn-primary" href="{{ route('client.services.index') }}">Explorer les services</a>
            </div>
        </div>
    @else
        <div class="request-card-list">
            @foreach($requests as $item)
                <article class="request-card {{ $item->status->requiresClientAction() ? 'request-card--needs-action' : '' }}">
                    <div class="request-card__top">
                        <div class="request-card__identity">
                            <span class="request-card__icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                            <div>
                                <p class="request-card__reference">PW-REQ-{{ str_pad((string) $item->id, 6, '0', STR_PAD_LEFT) }}</p>
                                <h3><a href="{{ route('client.requests.show', $item) }}">{{ $item->title }}</a></h3>
                                <p class="request-card__service">{{ $item->service?->title ?? 'Service indisponible' }}</p>
                            </div>
                        </div>
                        <span class="request-status-badge request-status-badge--{{ $item->status->value }}">
                            {{ $item->status->label() }}
                        </span>
                    </div>

                    <p class="request-card__description">{{ \Illuminate\Support\Str::limit($item->description, 190) }}</p>

                    <div class="request-card__metadata">
                        <span><i class="fa-regular fa-user" aria-hidden="true"></i> {{ $item->professional?->user?->name ?? 'Professionnel indisponible' }}</span>
                        <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Créée le {{ $item->created_at?->format('d/m/Y') }}</span>
                        @if($item->requested_at)
                            <span><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Envoyée le {{ $item->requested_at->format('d/m/Y') }}</span>
                        @endif
                        @if($item->budget_min !== null || $item->budget_max !== null)
                            <span><i class="fa-solid fa-wallet" aria-hidden="true"></i>
                                Budget :
                                @if($item->budget_min !== null){{ number_format((float) $item->budget_min, 2, ',', ' ') }}@else{{ '—' }}@endif
                                –
                                @if($item->budget_max !== null){{ number_format((float) $item->budget_max, 2, ',', ' ') }}@else{{ '—' }}@endif
                                {{ $item->currency }}
                            </span>
                        @endif
                    </div>

                    <div class="request-card__footer">
                        <div class="request-card__next-step {{ $item->status->requiresClientAction() ? 'request-card__next-step--action' : '' }}">
                            @if($item->status->value === 'draft')
                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i> Vérifiez les informations puis envoyez la demande.
                            @elseif($item->status->value === 'requested')
                                <i class="fa-regular fa-clock" aria-hidden="true"></i> En attente de la réponse du professionnel.
                            @elseif($item->status->value === 'quoted')
                                <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i> <strong>Action requise :</strong> un devis attend votre décision.
                            @elseif($item->status->value === 'accepted' && $item->order)
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Consultez la commande associée pour poursuivre.
                            @elseif($item->status->value === 'accepted')
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Demande acceptée ; la commande associée n’est pas encore disponible.
                            @elseif($item->status->value === 'rejected')
                                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> Cette demande a été refusée.
                            @else
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i> Cette demande est annulée.
                            @endif
                        </div>
                        <div class="request-card__actions">
                            @if($item->status->value === 'draft')
                                <a class="btn btn-secondary btn-sm" href="{{ route('client.requests.edit', $item) }}">Modifier</a>
                            @endif
                            @if($item->status->value === 'quoted' && $item->quotation)
                                <a class="btn btn-primary btn-sm" href="{{ route('client.quotes.show', $item->quotation) }}">Consulter le devis</a>
                            @elseif($item->status->value === 'accepted' && $item->order)
                                <a class="btn btn-primary btn-sm" href="{{ route('client.orders.show', $item->order) }}">Voir la commande</a>
                            @else
                                <a class="btn btn-primary btn-sm" href="{{ route('client.requests.show', $item) }}">Détails</a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="client-pagination">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
