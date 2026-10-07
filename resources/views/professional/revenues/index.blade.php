@extends('layouts.professional')

@section('title', 'Revenus')

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/orders-revenues.css', 'resources/js/pages/professional/orders-revenues.js'])
    @endpush
    <div class="page-header">
        <div>
            <p class="page-kicker">FINANCES</p>
            <h1>Mes revenus</h1>
            <p class="page-description">Les revenus sont calculés à partir des commissions effectivement comptabilisées.</p>
        </div>
    </div>

    <form class="filter-form" method="GET" action="{{ route('professional.revenues.index') }}">
        <label>Devise
            <select name="currency">
                <option value="">Toutes</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency }}" @selected($selectedCurrency === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </label>
        <label>Du <input type="date" name="from" value="{{ $from }}"></label>
        <label>Au <input type="date" name="to" value="{{ $to }}"></label>
        <button class="button button--primary" type="submit">Filtrer</button>
    </form>

    @if ($summary->isNotEmpty())
        <div class="summary-grid">
            @foreach ($summary as $row)
                <article class="metric-card">
                    <span>{{ $row->currency }}</span>
                    <strong>{{ number_format((float) $row->net_amount, 2) }}</strong>
                    <small>Net · {{ $row->orders_count }} transaction(s)</small>
                </article>
                <article class="metric-card">
                    <span>{{ $row->currency }}</span>
                    <strong>{{ number_format((float) $row->gross_amount, 2) }}</strong>
                    <small>Brut · commission {{ number_format((float) $row->commission_amount, 2) }}</small>
                </article>
            @endforeach
        </div>
    @endif

    <section class="data-card">
        <div class="section-heading"><h2>Historique financier</h2></div>
        @if ($commissions->isEmpty())
            <div class="empty-state"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><h2>Aucun revenu</h2><p>Les commissions postées apparaîtront ici.</p></div>
        @else
            <div class="table-wrap">
                <table class="professional-table">
                    <thead><tr><th>Date</th><th>Commande</th><th>Brut</th><th>Commission</th><th>Net</th><th>Statut</th></tr></thead>
                    <tbody>
                        @foreach ($commissions as $commission)
                            <tr>
                                <td>{{ $commission->posted_at?->format('d/m/Y') ?? '—' }}</td>
                                <td><a href="{{ route('professional.orders.show', $commission->order) }}">#{{ $commission->order_id }}</a></td>
                                <td>{{ number_format((float) $commission->gross_amount, 2) }} {{ $commission->currency }}</td>
                                <td>{{ number_format((float) $commission->commission_amount, 2) }}</td>
                                <td><strong>{{ number_format((float) $commission->net_amount, 2) }}</strong></td>
                                <td>{{ $commission->status->value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $commissions->links() }}</div>
        @endif
    </section>
@endsection
