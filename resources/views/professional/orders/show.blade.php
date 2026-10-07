@extends('layouts.professional')

@section('title', 'Commande #'.$order->id)

@section('dashboard_content')
    <div class="page-header">
        <div>
            <p class="page-kicker">COMMANDE #{{ $order->id }}</p>
            <h1>Détail de la commande</h1>
            <p class="page-description">Informations historiques et financières de la commande.</p>
        </div>
        <span class="status-badge">{{ str_replace('_', ' ', $order->status->value) }}</span>
    </div>

    <div class="detail-grid">
        <section class="data-card">
            <h2>Commande</h2>
            <dl class="detail-list">
                <div><dt>Client</dt><dd>{{ $order->client?->name ?? '—' }}</dd></div>
                <div><dt>Créée le</dt><dd>{{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt>Total</dt><dd>{{ number_format((float) $order->total, 2) }} {{ $order->currency ?? '' }}</dd></div>
                <div><dt>Service</dt><dd>{{ $order->serviceRequest?->service?->title ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="data-card">
            <h2>Finances</h2>
            <dl class="detail-list">
                <div><dt>Paiement</dt><dd>{{ $order->payment?->status?->value ?? 'Non disponible' }}</dd></div>
                <div><dt>Revenu brut</dt><dd>{{ $order->commission ? number_format((float) $order->commission->gross_amount, 2).' '.$order->commission->currency : '—' }}</dd></div>
                <div><dt>Commission</dt><dd>{{ $order->commission ? number_format((float) $order->commission->commission_amount, 2).' '.$order->commission->currency : '—' }}</dd></div>
                <div><dt>Net</dt><dd>{{ $order->commission ? number_format((float) $order->commission->net_amount, 2).' '.$order->commission->currency : '—' }}</dd></div>
            </dl>
        </section>

        <section class="data-card">
            <h2>Historique</h2>
            @forelse ($order->statusHistories as $history)
                <div class="timeline-item">
                    <strong>{{ str_replace('_', ' ', $history->status->value ?? $history->status) }}</strong>
                    <span>{{ $history->created_at?->format('d/m/Y H:i') }}</span>
                </div>
            @empty
                <p class="muted">Aucun historique disponible.</p>
            @endforelse
        </section>

        <section class="data-card">
            <h2>Adresse de la commande</h2>
            @if ($order->addressSnapshot)
                <p>{{ $order->addressSnapshot->address_line ?? $order->addressSnapshot->street ?? 'Adresse enregistrée dans le snapshot.' }}</p>
                <p class="muted">{{ $order->addressSnapshot->city ?? '' }} {{ $order->addressSnapshot->province ?? '' }}</p>
            @else
                <p class="muted">Aucun snapshot d'adresse disponible.</p>
            @endif
        </section>
    </div>
@endsection
