@extends('layouts.client')

@section('title', 'Reçu de simulation')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/client/orders-payments.js')
@endpush

@php($details = $receipt->payload)

<div class="client-module payment-receipt-page">
    <div class="payment-receipt-toolbar">
        <a class="btn btn-secondary" href="{{ route('client.payments.show', $payment) }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour au paiement</a>
        <button class="btn btn-primary" type="button" data-print-receipt><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimer / enregistrer en PDF</button>
    </div>

    <article class="payment-receipt-document">
        <header class="payment-receipt-document__header">
            <div class="payment-receipt-brand">
                <span class="payment-receipt-brand__mark" aria-hidden="true"><i class="fa-solid fa-handshake-angle"></i></span>
                <div><strong>PROXIWORK</strong><small>Services et prestations</small></div>
            </div>
            <span class="payment-simulation-badge"><i class="fa-solid fa-flask" aria-hidden="true"></i> REÇU DE SIMULATION</span>
        </header>

        <div class="payment-receipt-warning">
            <strong>SIMULATION — AUCUNE TRANSACTION FINANCIÈRE RÉELLE</strong>
            <p>Ce document confirme uniquement un événement enregistré dans le simulateur PROXIWORK. Il ne constitue pas une preuve de transfert Mobile Money, bancaire ou par carte réel.</p>
        </div>

        <section class="payment-receipt-document__title">
            <p class="client-module__eyebrow">CONFIRMATION DE PAIEMENT SIMULÉ</p>
            <h1>Reçu {{ $receipt->receipt_number }}</h1>
            <p>Émis le {{ optional($receipt->issued_at)->format('d/m/Y à H:i:s') }}</p>
        </section>

        <section class="payment-receipt-document__amount">
            <span>Montant confirmé dans la simulation</span>
            <strong>{{ number_format((float) $details['amount'], 2, ',', ' ') }} {{ $details['currency'] }}</strong>
            <span class="payment-receipt-confirmed"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Réussite simulée</span>
        </section>

        <section class="payment-receipt-document__section">
            <h2>Références</h2>
            <dl class="client-definition-list">
                <div><dt>Reçu</dt><dd>{{ $receipt->receipt_number }}</dd></div>
                <div><dt>Paiement</dt><dd>{{ $details['payment_reference'] }}</dd></div>
                <div><dt>Commande</dt><dd>{{ $details['order_reference'] }}</dd></div>
                <div><dt>Référence de tentative</dt><dd>{{ $details['transaction_reference'] }}</dd></div>
                <div><dt>Statut</dt><dd>Réussite simulée</dd></div>
            </dl>
        </section>

        <section class="payment-receipt-document__section">
            <h2>Prestation et participants</h2>
            <dl class="client-definition-list">
                <div><dt>Service</dt><dd>{{ $details['service_title'] }}</dd></div>
                <div><dt>Client</dt><dd>{{ $details['client_name'] }}</dd></div>
                @if(filled($details['professional_name'] ?? null))<div><dt>Professionnel</dt><dd>{{ $details['professional_name'] }}</dd></div>@endif
                <div><dt>Moyen simulé</dt><dd>{{ str_replace('_', ' ', $details['method']) }}</dd></div>
                <div><dt>Passerelle</dt><dd>Simulateur PROXIWORK</dd></div>
                <div><dt>Confirmation enregistrée</dt><dd>{{ $details['confirmed_at'] ? \Illuminate\Support\Carbon::parse($details['confirmed_at'])->format('d/m/Y à H:i:s') : '—' }}</dd></div>
            </dl>
        </section>

        <section class="payment-receipt-document__integrity">
            <h2>Vérification d’intégrité</h2>
            <p>Le reçu a été comparé aux données conservées par PROXIWORK avant affichage.</p>
            <span class="payment-receipt-verified"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Intégrité vérifiée côté serveur</span>
            <small>Empreinte SHA-256</small>
            <code>{{ $receipt->content_hash }}</code>
            <button class="payment-copy-hash" type="button" data-copy-value="{{ $receipt->content_hash }}" data-copy-hash>Copier l’empreinte</button>
            <span class="payment-copy-feedback" data-copy-feedback role="status" aria-live="polite"></span>
        </section>

        <footer class="payment-receipt-document__footer">
            <p>Ce reçu ne doit pas être présenté comme un reçu officiel d’un opérateur ou d’une banque. Une preuve de paiement réel nécessitera une confirmation vérifiée d’un prestataire de paiement réel.</p>
            <span>PROXIWORK · Mode simulation · {{ $receipt->receipt_number }}</span>
        </footer>
    </article>
</div>
@endsection
