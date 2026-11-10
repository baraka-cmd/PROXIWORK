@extends('layouts.admin')
@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/finance.css')
    @endunless
@endpush
@section('page_title', 'Portefeuille WAL-'.$wallet->id)
@section('page_description', 'Situation actuelle du portefeuille professionnel et aperçu de ses dernières écritures.')
@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.finance.wallets') }}">Retour aux portefeuilles</a>
@endsection
@section('content')
<div class="admin-finance admin-finance__detail-grid">
    <section class="admin-card"><h3>Propriétaire</h3><dl class="admin-finance__details">
        <div><dt>Professionnel</dt><dd>{{ $wallet->professional?->user?->name ?? '—' }}</dd></div>
        <div><dt>E-mail</dt><dd>{{ $wallet->professional?->user?->email ?? '—' }}</dd></div>
        <div><dt>Profil professionnel</dt><dd>#{{ $wallet->professional_id }}</dd></div>
        <div><dt>Référence portefeuille</dt><dd>WAL-{{ $wallet->id }}</dd></div>
        <div><dt>Devise</dt><dd>{{ $wallet->currency }}</dd></div>
        <div><dt>Statut</dt><dd>{{ $wallet->status }}</dd></div>
        <div><dt>Créé le</dt><dd>{{ $wallet->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
    </dl></section>
    <section class="admin-card"><h3>Soldes actuels</h3><dl class="admin-finance__details">
        <div><dt>Disponible</dt><dd>{{ number_format((float) $wallet->available_balance, 2, ',', ' ') }} {{ $wallet->currency }}</dd></div>
        <div><dt>En attente</dt><dd>{{ number_format((float) $wallet->pending_balance, 2, ',', ' ') }} {{ $wallet->currency }}</dd></div>
        <div><dt>Bloqué / réservé</dt><dd>{{ number_format((float) $wallet->locked_balance, 2, ',', ' ') }} {{ $wallet->currency }}</dd></div>
        <div><dt>Dernière mise à jour</dt><dd>{{ $wallet->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
    </dl><p class="admin-finance__notice">Ne modifiez jamais ces montants directement. Toute correction doit être une opération financière métier traçable.</p></section>
    <section class="admin-card admin-finance__full"><h3>Dernières écritures associées</h3><p class="admin-finance__muted">Aperçu limité aux huit dernières écritures. La consultation complète du registre est réservée au point 37.</p><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Référence</th><th>Type</th><th>Direction</th><th>Compartiment</th><th>Montant</th><th>Solde avant</th><th>Solde après</th><th>Date</th></tr></thead><tbody>
        @forelse($wallet->recentTransactions as $entry)
            <tr><td>#{{ $entry->id }}</td><td>{{ $entry->type->value ?? $entry->type }}</td><td>{{ $entry->direction->value ?? $entry->direction }}</td><td>{{ $entry->balance_bucket }}</td><td>{{ number_format((float) $entry->amount, 2, ',', ' ') }} {{ $entry->currency }}</td><td>{{ number_format((float) $entry->balance_before, 2, ',', ' ') }}</td><td>{{ number_format((float) $entry->balance_after, 2, ',', ' ') }}</td><td>{{ $entry->created_at?->format('d/m/Y H:i') }}</td></tr>
        @empty
                            <tr><td colspan="8"><div class="admin-empty">Aucune écriture enregistrée.</div></td></tr>
                        @endforelse
    </tbody></table></div></section>
</div>
@endsection
