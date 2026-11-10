@extends('layouts.admin')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/admin/payments.css')
    @endunless
@endpush

@section('page_title', 'Paiements')
@section('page_description', 'Suivez les paiements clients et vérifiez leur état réel sans modifier manuellement leur confirmation.')

@section('content')
<div class="admin-finance">
    <section class="admin-card">
        <div class="admin-card__header">
            <h3>Recherche et filtres</h3>
            <a class="admin-link" href="{{ route('admin.payments.index') }}">Réinitialiser</a>
        </div>
        <form method="GET" class="admin-filter-grid">
            <label class="admin-field admin-field--wide">
                <span>Recherche</span>
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Référence, commande, client ou professionnel">
            </label>
            <label class="admin-field">
                <span>Statut du paiement</span>
                <select name="status">
                    <option value="">Tous</option>
                    @foreach(\App\Enums\PaymentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>Statut de transaction</span>
                <select name="transaction_status">
                    <option value="">Tous</option>
                    @foreach(\App\Enums\PaymentTransactionStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['transaction_status'] ?? '') === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>Fournisseur</span>
                <select name="provider">
                    <option value="">Tous</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider }}" @selected(($filters['provider'] ?? '') === $provider)>{{ $provider }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>Devise</span>
                <input name="currency" maxlength="3" value="{{ $filters['currency'] ?? '' }}" placeholder="USD">
            </label>
            <label class="admin-field">
                <span>Montant minimum</span>
                <input type="number" min="0" step="0.01" name="min_amount" value="{{ $filters['min_amount'] ?? '' }}">
            </label>
            <label class="admin-field">
                <span>Montant maximum</span>
                <input type="number" min="0" step="0.01" name="max_amount" value="{{ $filters['max_amount'] ?? '' }}">
            </label>
            <label class="admin-field">
                <span>Du</span>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </label>
            <label class="admin-field">
                <span>Au</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </label>
            <div class="admin-filter-actions"><button class="button button--primary" type="submit">Appliquer</button></div>
        </form>
    </section>

    <section class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Paiement</th><th>Commande</th><th>Client</th><th>Professionnel</th><th>Montant</th><th>Fournisseur</th><th>Statut</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td><a href="{{ route('admin.payments.show', $payment) }}">PAY-{{ $payment->id }}</a><small>{{ $payment->created_at?->format('d/m/Y H:i') }}</small></td>
                            <td>@if(auth()->user()?->hasPermissionTo('orders.view'))<a href="{{ route('admin.orders.show', $payment->order) }}">{{ $payment->order?->order_number ?? '—' }}</a>@else{{ $payment->order?->order_number ?? '—' }}@endif</td>
                            <td>{{ $payment->client?->name ?? '—' }}</td>
                            <td>{{ $payment->order?->professional?->user?->name ?? '—' }}</td>
                            <td>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</td>
                            <td>{{ $payment->provider->value ?? '—' }}</td>
                            <td><span class="admin-status admin-status--{{ $payment->status->value }}">{{ $payment->status->value }}</span></td>
                            <td><a class="admin-icon-button" href="{{ route('admin.payments.show', $payment) }}" aria-label="Consulter le paiement">Voir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="admin-empty"><strong>Aucun paiement trouvé.</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
            <div class="admin-pagination">{{ $payments->links() }}</div>
        @endif
    </section>
</div>
@endsection
