@extends('layouts.client')

@section('title', 'Mes commandes')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
@endpush

<div class="client-module client-orders">
    <header class="client-module__header client-orders__intro">
        <div>
            <p class="client-module__eyebrow">ESPACE CLIENT · SUIVI DES PRESTATIONS</p>
            <h1>Mes commandes</h1>
            <p>Retrouvez chaque étape, les conditions convenues et les prochaines actions à effectuer.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('client.services.index') }}"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Découvrir les services</a>
    </header>

    <nav class="client-order-statistics" aria-label="Filtres rapides des commandes">
        <a class="client-order-stat {{ ($filters['bucket'] ?? 'all') === 'all' ? 'is-active' : '' }}" href="{{ route('client.orders.index', ['bucket' => 'all']) }}" @if(($filters['bucket'] ?? 'all') === 'all') aria-current="page" @endif>
            <span class="client-order-stat__icon" aria-hidden="true"><i class="fa-solid fa-layer-group"></i></span>
            <span class="client-order-stat__label">Toutes mes commandes</span>
            <strong>{{ $statistics['total'] }}</strong>
        </a>
        <a class="client-order-stat {{ ($filters['bucket'] ?? '') === 'active' ? 'is-active' : '' }}" href="{{ route('client.orders.index', ['bucket' => 'active']) }}" @if(($filters['bucket'] ?? '') === 'active') aria-current="page" @endif>
            <span class="client-order-stat__icon client-order-stat__icon--active" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
            <span class="client-order-stat__label">Confirmées / en cours</span>
            <strong>{{ $statistics['active'] }}</strong>
        </a>
        <a class="client-order-stat {{ ($filters['bucket'] ?? '') === 'pending_payment' ? 'is-active' : '' }}" href="{{ route('client.orders.index', ['bucket' => 'pending_payment']) }}" @if(($filters['bucket'] ?? '') === 'pending_payment') aria-current="page" @endif>
            <span class="client-order-stat__icon client-order-stat__icon--attention" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></span>
            <span class="client-order-stat__label">À payer</span>
            <strong>{{ $statistics['pending_payment'] }}</strong>
        </a>
        <a class="client-order-stat {{ ($filters['bucket'] ?? '') === 'completed' ? 'is-active' : '' }}" href="{{ route('client.orders.index', ['bucket' => 'completed']) }}" @if(($filters['bucket'] ?? '') === 'completed') aria-current="page" @endif>
            <span class="client-order-stat__icon client-order-stat__icon--completed" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
            <span class="client-order-stat__label">Terminées</span>
            <strong>{{ $statistics['completed'] }}</strong>
        </a>
    </nav>

    <section class="client-panel client-order-filters" aria-labelledby="client-order-filters-title">
        <div class="client-panel__header">
            <div>
                <h2 id="client-order-filters-title">Rechercher une commande</h2>
                <p>Les résultats sont filtrés sur le serveur et restent limités à votre compte.</p>
            </div>
            <a class="client-filter-clear" href="{{ route('client.orders.index') }}">Effacer les filtres</a>
        </div>

        <form method="GET" action="{{ route('client.orders.index') }}" class="client-order-filter-form">
            <div class="client-field client-order-search">
                <label for="order-q">Recherche</label>
                <input id="order-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Référence, service ou professionnel">
            </div>

            <div class="client-field">
                <label for="order-status">Statut de la commande</label>
                <select id="order-status" name="status">
                    <option value="">Tous les statuts</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="client-field">
                <label for="order-payment-status">Statut du paiement</label>
                <select id="order-payment-status" name="payment_status">
                    <option value="">Tous les paiements</option>
                    @foreach($paymentStatusLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="client-field">
                <label for="order-professional">Professionnel</label>
                <select id="order-professional" name="professional_id">
                    <option value="">Tous les professionnels</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected((string) ($filters['professional_id'] ?? '') === (string) $professional->id)>
                            {{ $professional->user?->name ?? $professional->professional_title ?? 'Professionnel' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="client-field">
                <label for="order-service">Service</label>
                <select id="order-service" name="service_id">
                    <option value="">Tous les services</option>
                    @foreach($services as $service)
                        <option value="{{ $service->service_id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $service->service_id)>{{ $service->service_title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="client-field">
                <label for="order-created-from">Créée à partir du</label>
                <input id="order-created-from" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </div>

            <div class="client-field">
                <label for="order-created-to">Créée jusqu’au</label>
                <input id="order-created-to" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}" min="{{ $filters['created_from'] ?? '' }}">
            </div>

            @if(filled($filters['bucket'] ?? null))
                <input type="hidden" name="bucket" value="{{ $filters['bucket'] }}">
            @endif

            <div class="client-order-filter-actions">
                <p><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Le statut de la commande et celui du paiement sont affichés séparément.</p>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Appliquer les filtres</button>
            </div>
        </form>
    </section>

    <div class="client-order-results-heading">
        <div>
            <h2>Résultats</h2>
            <p><strong>{{ $orders->total() }}</strong> {{ $orders->total() > 1 ? 'commandes correspondent' : 'commande correspond' }} à votre recherche</p>
        </div>
        @if(filled($filters['q'] ?? null) || filled($filters['status'] ?? null) || filled($filters['payment_status'] ?? null) || filled($filters['professional_id'] ?? null) || filled($filters['service_id'] ?? null) || filled($filters['created_from'] ?? null) || filled($filters['created_to'] ?? null) || (($filters['bucket'] ?? 'all') !== 'all'))
            <a class="client-filter-clear" href="{{ route('client.orders.index') }}">Réinitialiser la recherche</a>
        @endif
    </div>

    @if($orders->isEmpty())
        @if(($statistics['total'] ?? 0) === 0)
            <section class="client-panel client-order-empty" aria-labelledby="orders-empty-title">
                <span class="client-order-empty__icon" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></span>
                <h2 id="orders-empty-title">Votre première commande apparaîtra ici</h2>
                <p>Lorsque vous accepterez un devis, vous pourrez suivre le paiement, le planning et la livraison depuis cet espace.</p>
                <a class="btn btn-primary" href="{{ route('client.services.index') }}">Explorer les services</a>
            </section>
        @else
            <section class="client-panel client-order-empty" aria-labelledby="orders-empty-title">
                <span class="client-order-empty__icon" aria-hidden="true"><i class="fa-solid fa-filter-circle-xmark"></i></span>
                <h2 id="orders-empty-title">Aucune commande ne correspond à ces critères</h2>
                <p>Essayez une autre recherche ou retirez les filtres pour retrouver vos commandes.</p>
                <a class="btn btn-primary" href="{{ route('client.orders.index') }}">Effacer les filtres</a>
            </section>
        @endif
    @else
        <div class="client-list client-order-list">
            @foreach($orders as $order)
                <article class="client-list-item client-order-card">
                    <div class="client-list-item__main">
                        <div class="client-list-item__icon" aria-hidden="true"><i class="fa-solid fa-bag-shopping"></i></div>
                        <div class="client-order-card__body">
                            <div class="client-order-card__title-row">
                                <h3>{{ $order->items->first()?->service_title ?? 'Prestation de service' }}</h3>
                                <span class="status-badge status-badge--{{ $order->status->value }}">{{ $statusLabels[$order->status->value] ?? 'Statut à vérifier' }}</span>
                            </div>
                            <p class="client-order-reference">Référence : <strong>{{ $order->order_number ?? '#'.$order->id }}</strong></p>
                            <p>Professionnel : <strong>{{ $order->professional?->user?->name ?? $order->professional?->professional_title ?? 'Non disponible' }}</strong></p>
                            <div class="client-order-card__meta">
                                <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Créée le {{ optional($order->created_at)->format('d/m/Y') }}</span>
                                <span><i class="fa-solid fa-coins" aria-hidden="true"></i> {{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</span>
                            </div>
                            <p class="client-order-payment-state">
                                <span> Paiement : </span>
                                @if($order->payment)
                                    <span class="status-badge status-badge--{{ $order->payment->status->value }}">{{ $paymentStatusLabels[$order->payment->status->value] ?? 'État du paiement à vérifier' }}</span>
                                @else
                                    <span class="client-order-muted">Aucun paiement enregistré</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="client-list-item__side client-order-card__actions">
                        @if($order->status->value === 'pending_payment' && (!$order->payment || in_array($order->payment->status->value, ['failed', 'cancelled', 'expired'], true)))
                            <a class="btn btn-primary btn-sm" href="{{ route('client.orders.payment', $order) }}">Accéder au paiement</a>
                        @elseif($order->payment)
                            <a class="btn btn-secondary btn-sm" href="{{ route('client.payments.show', $order->payment) }}">Consulter le paiement</a>
                        @endif
                        <a class="btn btn-secondary btn-sm" href="{{ route('client.orders.show', $order) }}">Suivre la commande <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="client-pagination" aria-label="Pagination des commandes">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
