@extends('layouts.professional')

@section('title', 'Commande #'.$order->id)

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/orders-revenues.css', 'resources/js/pages/professional/orders-revenues.js'])
    @endpush
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
                <address class="order-address-snapshot">
                    @if($order->addressSnapshot->recipient_name)<strong>{{ $order->addressSnapshot->recipient_name }}</strong>@endif
                    @if($order->addressSnapshot->contact_phone)<span>{{ $order->addressSnapshot->contact_phone }}</span>@endif
                    <span>{{ $order->addressSnapshot->address_line_1 }}{{ $order->addressSnapshot->address_line_2 ? ', '.$order->addressSnapshot->address_line_2 : '' }}</span>
                    <span>{{ collect([$order->addressSnapshot->neighborhood, $order->addressSnapshot->commune, $order->addressSnapshot->city, $order->addressSnapshot->province, $order->addressSnapshot->country_code])->filter()->join(', ') }}</span>
                    @if($order->addressSnapshot->landmark)<span><strong>Repère :</strong> {{ $order->addressSnapshot->landmark }}</span>@endif
                    @if($order->addressSnapshot->access_instructions)<span><strong>Instructions d’accès :</strong> {{ $order->addressSnapshot->access_instructions }}</span>@endif
                </address>
            @else
                @if($order->serviceRequest?->execution_mode?->value === 'remote')
                    <p class="muted">Prestation à distance : aucune adresse physique du client n’est requise.</p>
                @else
                    <p class="muted">Aucun snapshot d'adresse disponible.</p>
                @endif
            @endif
        </section>
    </div>
@endsection
