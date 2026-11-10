@extends('layouts.admin')
@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/finance.css')
    @endunless
@endpush
@section('page_title', 'Transaction TX-'.$transaction->id)
@section('page_description', 'Détail en lecture seule et événements de traçabilité associés.')
@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.finance.transactions') }}">Retour aux transactions</a>
@endsection
@section('content')
<div class="admin-finance admin-finance__detail-grid">
    <section class="admin-card"><h3>Transaction</h3><dl class="admin-finance__details">
        <div><dt>Référence</dt><dd>TX-{{ $transaction->id }}</dd></div>
        <div><dt>Statut</dt><dd><span class="admin-status admin-status--{{ $transaction->status->value }}">{{ $transaction->status->value }}</span></dd></div>
        <div><dt>Montant</dt><dd>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</dd></div>
        <div><dt>Fournisseur</dt><dd>{{ $transaction->provider }}</dd></div>
        <div><dt>Référence fournisseur</dt><dd>{{ $transaction->provider_reference ?: 'Non fournie' }}</dd></div>
        <div><dt>Identifiant fournisseur</dt><dd>{{ $transaction->provider_transaction_id ?: 'Non fourni' }}</dd></div>
        <div><dt>Initiée le</dt><dd>{{ $transaction->initiated_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        <div><dt>Traitée le</dt><dd>{{ $transaction->processed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        <div><dt>Code d'échec</dt><dd>{{ $transaction->failure_code ?: '—' }}</dd></div>
        <div><dt>Motif d'échec</dt><dd>{{ $transaction->failure_message ?: '—' }}</dd></div>
    </dl></section>
    <section class="admin-card"><h3>Contexte commercial</h3><dl class="admin-finance__details">
        <div><dt>Paiement</dt><dd><a href="{{ route('admin.payments.show', $transaction->payment) }}">#{{ $transaction->payment_id }}</a></dd></div>
        <div><dt>Commande</dt><dd>{{ $transaction->payment?->order?->order_number ?? '—' }}</dd></div>
        <div><dt>Client</dt><dd>{{ $transaction->payment?->client?->name ?? '—' }}</dd></div>
        <div><dt>Professionnel</dt><dd>{{ $transaction->payment?->order?->professional?->user?->name ?? '—' }}</dd></div>
        <div><dt>Créée le</dt><dd>{{ $transaction->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
    </dl><p class="admin-finance__notice">Les clés d'idempotence, métadonnées brutes et secrets du fournisseur ne sont pas exposés dans cette interface.</p></section>
    <section class="admin-card admin-finance__full"><h3>Événements enregistrés</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Événement</th><th>Transition</th><th>Source</th><th>Date</th></tr></thead><tbody>
        @forelse($transaction->adminEvents as $event)
            <tr><td>{{ $event->event_type }}</td><td>{{ $event->from_status ?: '—' }} → {{ $event->to_status }}</td><td>{{ $event->source }}</td><td>{{ $event->occurred_at?->format('d/m/Y H:i:s') }}</td></tr>
        @empty
                            <tr><td colspan="4"><div class="admin-empty">Aucun événement associé.</div></td></tr>
                        @endforelse
    </tbody></table></div></section>
</div>
@endsection
