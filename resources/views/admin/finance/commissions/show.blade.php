@extends('layouts.admin')
@push('head')@unless(app()->environment('testing'))@vite('resources/css/pages/admin/finance.css')@endunless@endpush
@section('page_title', 'Commission COM-'.$commission->id)
@section('page_description', 'Détail du calcul historique et vérification de son écriture de portefeuille.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.finance.commissions') }}">Retour aux commissions</a>@endsection
@section('content')
<div class="admin-finance admin-finance__detail-grid">
    <section class="admin-card"><h3>Calcul enregistré</h3><dl class="admin-finance__details">
        <div><dt>Statut</dt><dd><span class="admin-status admin-status--{{ $commission->status->value }}">{{ $commission->status->value }}</span></dd></div>
        <div><dt>Montant brut</dt><dd>{{ number_format((float) $commission->gross_amount, 2, ',', ' ') }} {{ $commission->currency }}</dd></div>
        <div><dt>Taux historique appliqué</dt><dd>{{ number_format((float) $commission->commission_rate, 2, ',', ' ') }} %</dd></div>
        <div><dt>Commission PROXIWORK</dt><dd>{{ number_format((float) $commission->commission_amount, 2, ',', ' ') }} {{ $commission->currency }}</dd></div>
        <div><dt>Net professionnel</dt><dd>{{ number_format((float) $commission->net_amount, 2, ',', ' ') }} {{ $commission->currency }}</dd></div>
        <div><dt>Comptabilisée le</dt><dd>{{ $commission->posted_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        <div><dt>Inversée le</dt><dd>{{ $commission->reversed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
    </dl><p class="admin-finance__notice">Le taux historique enregistré est la référence de cette opération. Le taux actuellement configuré ({{ number_format((float) $configuredRate, 2, ',', ' ') }} %) ne le remplace pas.</p></section>
    <section class="admin-card"><h3>Origine</h3><dl class="admin-finance__details">
        <div><dt>Commande</dt><dd>{{ $commission->order?->order_number ?? '#'.$commission->order_id }}</dd></div>
        <div><dt>Client</dt><dd>{{ $commission->order?->client?->name ?? '—' }}</dd></div>
        <div><dt>Professionnel</dt><dd>{{ $commission->professional?->user?->name ?? '—' }}</dd></div>
        <div><dt>Paiement</dt><dd>{{ $commission->payment ? '#'.$commission->payment->id.' · '.$commission->payment->status->value : '—' }}</dd></div>
        <div><dt>Type de calcul</dt><dd>{{ $commission->calculation_type }}</dd></div>
    </dl></section>
    <section class="admin-card admin-finance__full"><h3>Écritures de portefeuille associées</h3><p class="admin-finance__muted">Aperçu des écritures de cette commission. Le registre complet sera consultable dans l'espace dédié aux mouvements financiers.</p><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Écriture</th><th>Type</th><th>Direction</th><th>Compartiment</th><th>Montant</th><th>Date</th></tr></thead><tbody>
        @forelse($commission->walletEntries as $entry)
            <tr><td>#{{ $entry->id }}</td><td>{{ $entry->type->value ?? $entry->type }}</td><td>{{ $entry->direction->value ?? $entry->direction }}</td><td>{{ $entry->balance_bucket }}</td><td>{{ number_format((float) $entry->amount, 2, ',', ' ') }} {{ $entry->currency }}</td><td>{{ $entry->created_at?->format('d/m/Y H:i') }}</td></tr>
        @empty<tr><td colspan="6"><div class="admin-empty">Aucune écriture de portefeuille retrouvée pour cette commission.</div></td></tr>@endforelse
    </tbody></table></div></section>
</div>
@endsection
