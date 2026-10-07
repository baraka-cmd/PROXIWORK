@extends('layouts.professional')

@section('title', 'Commandes')

@section('dashboard_content')
    <div class="page-header">
        <div>
            <p class="page-kicker">ACTIVITÉ</p>
            <h1>Mes commandes</h1>
            <p class="page-description">Suivez les commandes qui vous sont attribuées.</p>
        </div>
    </div>

    <div class="filter-bar" aria-label="Filtrer les commandes">
        <a href="{{ route('professional.orders.index') }}" class="filter-chip {{ $selectedStatus === '' ? 'is-active' : '' }}">Toutes</a>
        @foreach ($statuses as $status)
            <a href="{{ route('professional.orders.index', ['status' => $status->value]) }}" class="filter-chip {{ $selectedStatus === $status->value ? 'is-active' : '' }}">
                {{ str_replace('_', ' ', ucfirst($status->value)) }}
            </a>
        @endforeach
    </div>

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
