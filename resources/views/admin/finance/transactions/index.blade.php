@extends('layouts.admin')
@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/finance.css')
    @endunless
@endpush
@section('page_eyebrow', 'FINANCES · TRAÇABILITÉ')
@section('page_title', 'Transactions de paiement')
@section('page_description', 'Consultez les opérations techniques associées aux paiements. Cet écran est en lecture seule.')
@section('content')
<div class="admin-finance">
    <section class="admin-card">
        <div class="admin-card__header"><h3>Recherche et filtres</h3><a class="admin-link" href="{{ route('admin.finance.transactions') }}">Réinitialiser</a></div>
        <form method="GET" class="admin-filter-grid">
            <label class="admin-field admin-field--wide"><span>Recherche</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Référence, commande, client, professionnel"></label>
            <label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(\App\Enums\PaymentTransactionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->value }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Fournisseur</span><input name="provider" value="{{ $filters['provider'] ?? '' }}" placeholder="fake"></label>
            <label class="admin-field"><span>Devise</span><input name="currency" maxlength="3" value="{{ $filters['currency'] ?? '' }}" placeholder="USD"></label>
            <label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <div class="admin-filter-actions"><button class="button button--primary">Appliquer</button></div>
        </form>
    </section>
    <section class="admin-card">
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Transaction</th><th>Paiement / commande</th><th>Client</th><th>Professionnel</th><th>Montant</th><th>Fournisseur</th><th>Statut</th><th></th></tr></thead><tbody>
        @forelse($transactions as $transaction)
            <tr>
                <td><a href="{{ route('admin.finance.transactions.show', $transaction) }}">TX-{{ $transaction->id }}</a><small>{{ $transaction->created_at?->format('d/m/Y H:i') }}</small></td>
                <td>#{{ $transaction->payment_id }} · {{ $transaction->payment?->order?->order_number ?? '—' }}</td>
                <td>{{ $transaction->payment?->client?->name ?? '—' }}</td>
                <td>{{ $transaction->payment?->order?->professional?->user?->name ?? '—' }}</td>
                <td>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</td>
                <td>{{ $transaction->provider }}</td>
                <td><span class="admin-status admin-status--{{ $transaction->status->value }}">{{ $transaction->status->value }}</span></td>
                <td><a class="admin-icon-button" href="{{ route('admin.finance.transactions.show', $transaction) }}" aria-label="Consulter la transaction">Voir</a></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="admin-empty">Aucune transaction trouvée.</div></td></tr>
        @endforelse
        </tbody></table></div>
        @if ($transactions->hasPages())
            <div class="admin-pagination">{{ $transactions->links() }}</div>
        @endif
    </section>
</div>
@endsection
