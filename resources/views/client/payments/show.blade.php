@extends('layouts.client')
@section('title','Paiement')
@section('content')
@push('head')@vite('resources/css/pages/client/orders-payments.css')@endpush
<div class="client-module"><div class="client-module__header"><div><p class="client-module__eyebrow">PAIEMENT</p><h1>Suivi du paiement</h1><p>{{ $payment->order?->order_number ?? '#'.$payment->order_id }}</p></div><span class="status-badge status-badge--{{ $payment->status->value }}">{{ ucfirst($payment->status->value) }}</span></div>
@if(session('success'))<p class="client-panel">{{ session('success') }}</p>@endif
<section class="client-panel"><dl class="client-definition-list"><div><dt>Montant</dt><dd>{{ number_format((float)$payment->amount,2,',',' ') }} {{ $payment->currency }}</dd></div><div><dt>Méthode</dt><dd>{{ $payment->method->value ?? '—' }}</dd></div><div><dt>Fournisseur</dt><dd>{{ $payment->provider->value ?? '—' }}</dd></div><div><dt>Payé le</dt><dd>{{ optional($payment->paid_at)->format('d/m/Y à H:i') ?: '—' }}</dd></div></dl></section>
<section class="client-panel"><h2>Transactions</h2><div class="client-list">@forelse($payment->transactions as $transaction)<article class="client-list-item"><div><strong>{{ $transaction->provider_reference ?? 'Transaction #'.$transaction->id }}</strong><p>{{ data_get($transaction->status,'value',$transaction->status) }}</p></div><small>{{ optional($transaction->created_at)->format('d/m/Y à H:i') }}</small></article>@empty<p>Aucune transaction enregistrée.</p>@endforelse</div></section>
<a class="btn btn-secondary" href="{{ route('client.orders.show',$payment->order) }}">Retour à la commande</a></div>
@endsection