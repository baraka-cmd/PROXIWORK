@extends('layouts.admin')
@section('page_eyebrow','FINANCES')
@section('page_title','Détail du mouvement')
@section('page_description','Consultation seule d’une écriture immuable du portefeuille.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.finance.movements') }}">Retour au registre</a>@endsection
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/admin/finance-operations.css'])
@endunless
@endpush
@section('content')
@php($movementTypeLabels = ['earning' => 'Revenu professionnel', 'commission' => 'Commission', 'withdrawal' => 'Retrait', 'refund' => 'Remboursement', 'adjustment' => 'Ajustement', 'reversal' => 'Contre-écriture'])
@php($bucketLabels = ['available' => 'Disponible', 'pending' => 'En attente', 'locked' => 'Bloqué'])
<div class="admin-financial-operations">
    <section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">ÉCRITURE #{{ $movement->id }}</span><h3>{{ \App\Support\MoneyFormatter::format((string)$movement->amount) }} {{ $movement->currency }}</h3></div><span class="admin-page-badge">{{ $movement->direction->value === 'credit' ? 'Crédit' : 'Débit' }}</span></div>
    <dl class="admin-financial-details">
        <div><dt>Date</dt><dd>{{ $movement->created_at?->format('d/m/Y H:i:s') }}</dd></div>
        <div><dt>Professionnel</dt><dd>{{ $movement->wallet?->professional?->user?->name ?? '—' }} · {{ $movement->wallet?->professional?->user?->email ?? '—' }}</dd></div>
        <div><dt>Portefeuille</dt><dd>#{{ $movement->wallet_id }} · {{ $movement->currency }}</dd></div>
        <div><dt>Type</dt><dd>{{ $movementTypeLabels[$movement->type->value] ?? $movement->type->value }}</dd></div>
        <div><dt>Compartiment</dt><dd>{{ $bucketLabels[$movement->balance_bucket] ?? '—' }}</dd></div>
        <div><dt>Montant</dt><dd>{{ \App\Support\MoneyFormatter::format((string)$movement->amount) }} {{ $movement->currency }}</dd></div>
        <div><dt>Solde avant</dt><dd>{{ \App\Support\MoneyFormatter::format((string)$movement->balance_before) }} {{ $movement->currency }}</dd></div>
        <div><dt>Solde après</dt><dd>{{ \App\Support\MoneyFormatter::format((string)$movement->balance_after) }} {{ $movement->currency }}</dd></div>
        <div><dt>Opération d'origine</dt><dd>{{ class_basename($movement->reference_type ?? '') }} #{{ $movement->reference_id ?? '—' }}</dd></div>
        <div><dt>Description</dt><dd>{{ $movement->description ?: '—' }}</dd></div>
    </dl></section>
    <div class="admin-alert admin-alert--warning" role="note"><i class="fa-solid fa-lock" aria-hidden="true"></i><div><strong>Aucune modification possible</strong><p>Cette page ne modifie pas les soldes. Elle expose uniquement les colonnes de registre autorisées; les métadonnées internes et clés d’idempotence restent privées.</p></div></div>
</div>
@endsection
