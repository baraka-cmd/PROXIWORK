@extends('layouts.professional')

@section('title', 'Commandes')

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/orders-revenues.css', 'resources/js/pages/professional/orders-revenues.js'])
    @endpush
    <div class="page-header">
        <div>
            <p class="page-kicker">ACTIVITÉ</p>
            <h1>Mes commandes</h1>
            <p class="page-description">Suivez les commandes qui vous sont attribuées.</p>
        </div>
    </div>

    <nav class="filter-bar" aria-label="Filtrer les commandes par état">
        <a href="{{ route('professional.orders.index', array_filter(['from' => $from, 'to' => $to])) }}" class="filter-chip {{ $selectedStatus === '' ? 'is-active' : '' }}">Toutes</a>
        <a href="{{ route('professional.orders.index', array_filter(['status' => 'active', 'from' => $from, 'to' => $to])) }}" class="filter-chip {{ $selectedStatus === 'active' ? 'is-active' : '' }}">Actives</a>
        @foreach ($statuses as $status)
            <a href="{{ route('professional.orders.index', array_filter(['status' => $status->value, 'from' => $from, 'to' => $to])) }}" class="filter-chip {{ $selectedStatus === $status->value ? 'is-active' : '' }}">
                {{ str_replace('_', ' ', ucfirst($status->value)) }}
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
        <div class="data-card">
            <div class="table-wrap">
                <table class="professional-table">
                    <thead>
                        <tr><th>Commande</th><th>Client</th><th>Statut</th><th>Montant</th><th>Date</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><strong>#{{ $order->id }}</strong></td>
                                <td>{{ $order->client?->name ?? 'Client' }}</td>
                                <td><span class="status-badge">{{ str_replace('_', ' ', $order->status->value) }}</span></td>
                                <td>{{ number_format((float) $order->total, 2) }} {{ $order->currency ?? '' }}</td>
                                <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                                <td><a class="button button--secondary button--small" href="{{ route('professional.orders.show', $order) }}">Voir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $orders->links() }}</div>
        </div>
    @endif
@endsection
