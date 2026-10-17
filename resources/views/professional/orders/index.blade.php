@extends('layouts.professional')

@section('title', 'Commandes')

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/orders-revenues.css', 'resources/js/pages/professional/orders-revenues.js'])
    @endpush
    <div class="page-header professional-orders-page-header">
        <div>
            <p class="page-kicker">ESPACE PROFESSIONNEL · ACTIVITÉ</p>
            <h1>Mes commandes</h1>
            <p class="page-description">Retrouvez les prochaines actions, les échéances et l’état réel de chaque commande.</p>
        </div>
    </div>

    <section class="summary-grid professional-order-priorities" aria-label="Résumé de l’activité des commandes">
        <article class="data-card metric-card"><span>Commandes confirmées</span><strong>{{ number_format($statistics['confirmed']) }}</strong><small>À planifier ou rendez-vous convenu</small></article>
        <article class="data-card metric-card"><span>En réalisation</span><strong>{{ number_format($statistics['in_progress']) }}</strong><small>Travaux en cours</small></article>
        <article class="data-card metric-card"><span>À faire réceptionner</span><strong>{{ number_format($statistics['delivery_submitted']) }}</strong><small>Décision du client attendue</small></article>
        <article class="data-card metric-card"><span>En attente de paiement</span><strong>{{ number_format($statistics['pending_payment']) }}</strong><small>Le démarrage reste bloqué</small></article>
        @if ($statistics['disputed'] > 0)
            <article class="data-card metric-card professional-order-priorities__alert"><span>Contestées</span><strong>{{ number_format($statistics['disputed']) }}</strong><small>Suivi nécessaire</small></article>
        @endif
    </section>

    <nav class="filter-bar" aria-label="Filtrer les commandes par état">
        <a href="{{ route('professional.orders.index', array_filter(['from' => $from, 'to' => $to])) }}" class="filter-chip {{ $selectedStatus === '' ? 'is-active' : '' }}">Toutes</a>
        <a href="{{ route('professional.orders.index', array_filter(['status' => 'active', 'from' => $from, 'to' => $to])) }}" class="filter-chip {{ $selectedStatus === 'active' ? 'is-active' : '' }}">Actives</a>
        @foreach ($statuses as $status)
            <a href="{{ route('professional.orders.index', array_filter(['status' => $status->value, 'from' => $from, 'to' => $to])) }}" class="filter-chip {{ $selectedStatus === $status->value ? 'is-active' : '' }}">
                {{ $statusLabels[$status->value] ?? str_replace('_', ' ', ucfirst($status->value)) }}
            </a>
        @endforeach
    </nav>

    <form class="filter-bar professional-order-date-filters" method="GET" action="{{ route('professional.orders.index') }}" aria-label="Filtrer les commandes par date">
        @if ($selectedStatus !== '')
            <input type="hidden" name="status" value="{{ $selectedStatus }}">
        @endif
        <label for="orders-from">Du
            <input id="orders-from" type="date" name="from" value="{{ $from }}">
        </label>
        <label for="orders-to">Au
            <input id="orders-to" type="date" name="to" value="{{ $to }}">
        </label>
        <button class="button button--secondary button--sm" type="submit">Appliquer les dates</button>
        @if ($from !== '' || $to !== '')
            <a class="button button--ghost button--sm" href="{{ route('professional.orders.index', $selectedStatus !== '' ? ['status' => $selectedStatus] : []) }}">Effacer les dates</a>
        @endif
    </form>
    <p class="page-description">Pour les commandes terminées, les dates filtrent la date de fin de prestation. Pour les autres états, elles filtrent la date de création.</p>

    @if ($orders->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
            <h2>Aucune commande</h2>
            <p>Les commandes liées à vos services apparaîtront ici.</p>
        </div>
    @else
        <div class="data-card professional-order-list-card">
            <div class="table-wrap professional-order-table-wrap">
                <table class="professional-table professional-order-table">
                    <thead>
                        <tr><th>Commande</th><th>Client</th><th>Statut</th><th>Montant</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr @class(['professional-order-row--priority' => in_array($order->status->value, ['pending_payment', 'delivery_submitted', 'disputed'], true)])>
                                <td data-label="Commande"><strong>{{ $order->order_number ?? ('#'.$order->getKey()) }}</strong><small class="table-secondary">Référence interne #{{ $order->getKey() }}</small></td>
                                <td data-label="Client">{{ $order->client?->name ?? 'Client' }}</td>
                                <td data-label="Statut"><span class="status-badge status-badge--{{ $order->status->value }}">{{ $statusLabels[$order->status->value] ?? str_replace('_', ' ', $order->status->value) }}</span></td>
                                <td data-label="Total contractuel"><strong>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency ?? '' }}</strong></td>
                                <td data-label="Dates">{{ $order->created_at?->format('d/m/Y') }}@if($order->serviceRequest?->desired_at)<small class="table-secondary">Souhait client : {{ $order->serviceRequest->desired_at->format('d/m/Y') }}</small>@endif</td>
                                <td data-label="Action"><a class="button button--secondary button--small" href="{{ route('professional.orders.show', $order) }}">Consulter</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $orders->links() }}</div>
        </div>
    @endif
@endsection
