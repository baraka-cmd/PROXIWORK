@extends('layouts.admin')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/payments.css')
    @endunless
@endpush

@section('page_title', 'Paiement PAY-'.$payment->id)
@section('page_description', 'Dossier de paiement, commande associée, transactions et événements vérifiables.')
@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.payments.index') }}">Retour aux paiements</a>
@endsection

@section('content')
<div class="admin-finance admin-finance__detail-grid">
    <section class="admin-card">
        <h3>Paiement</h3>
        <dl class="admin-finance__details">
            <div><dt>Référence</dt><dd>PAY-{{ $payment->id }}</dd></div>
            <div><dt>Statut</dt><dd><span class="admin-status admin-status--{{ $payment->status->value }}">{{ $payment->status->value }}</span></dd></div>
            <div><dt>Montant</dt><dd>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</dd></div>
            <div><dt>Méthode</dt><dd>{{ $payment->method->value ?? $payment->method ?? '—' }}</dd></div>
            <div><dt>Fournisseur</dt><dd>{{ $payment->provider->value ?? $payment->provider ?? '—' }}</dd></div>
            <div><dt>Créé le</dt><dd>{{ $payment->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Confirmé le</dt><dd>{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Échoué le</dt><dd>{{ $payment->failed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Annulé le</dt><dd>{{ $payment->cancelled_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="admin-card">
        <h3>Parties et commande</h3>
        <dl class="admin-finance__details">
            <div><dt>Commande</dt><dd><a href="{{ route('admin.orders.show', $payment->order) }}">{{ $payment->order?->order_number ?? '#'.$payment->order_id }}</a></dd></div>
            <div><dt>Client</dt><dd>{{ $payment->client?->name ?? '—' }}</dd></div>
            <div><dt>E-mail client</dt><dd>{{ $payment->client?->email ?? '—' }}</dd></div>
            <div><dt>Professionnel</dt><dd>{{ $payment->order?->professional?->user?->name ?? '—' }}</dd></div>
            <div><dt>Statut commande</dt><dd>{{ $payment->order?->status?->value ?? '—' }}</dd></div>
            <div><dt>Commission</dt><dd>@if($payment->commission)@if(auth()->user()?->hasPermissionTo('commissions.view'))<a href="{{ route('admin.finance.commissions.show', $payment->commission) }}">COM-{{ $payment->commission->id }}</a>@else COM-{{ $payment->commission->id }}@endif @else Non comptabilisée @endif</dd></div>
        </dl>
    </section>

    <section class="admin-card admin-finance__full">
        <h3>Transactions de paiement</h3>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Référence</th><th>Fournisseur</th><th>Montant</th><th>Statut</th><th>Référence fournisseur</th><th>Traitée le</th></tr></thead>
                <tbody>
                    @forelse($payment->transactions as $transaction)
                        <tr>
                            <td>@if(auth()->user()?->hasPermissionTo('transactions.view'))<a href="{{ route('admin.finance.transactions.show', $transaction) }}">TX-{{ $transaction->id }}</a>@else TX-{{ $transaction->id }}@endif</td>
                            <td>{{ $transaction->provider }}</td>
                            <td>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</td>
                            <td><span class="admin-status admin-status--{{ $transaction->status->value }}">{{ $transaction->status->value }}</span></td>
                            <td>{{ $transaction->provider_reference ?: '—' }}</td>
                            <td>{{ $transaction->processed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="admin-empty">Aucune transaction enregistrée.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="admin-finance__notice">Les clés d’idempotence, secrets et métadonnées brutes du fournisseur restent masqués.</p>
    </section>

    <section class="admin-card admin-finance__full">
        <h3>Historique des événements</h3>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Événement</th><th>Transition</th><th>Source</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($payment->events as $event)
                        <tr><td>{{ $event->event_type }}</td><td>{{ $event->from_status ?? '—' }} → {{ $event->to_status }}</td><td>{{ $event->source }}</td><td>{{ $event->occurred_at?->format('d/m/Y H:i:s') }}</td></tr>
                    @empty
                        <tr><td colspan="4"><div class="admin-empty">Aucun événement enregistré.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
