@extends('layouts.professional')
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/professional/wallet-withdrawals.css','resources/js/pages/professional/wallet-withdrawals.js'])
@endunless
@endpush
@section('page_eyebrow','FINANCES')
@section('page_title','Portefeuille')
@section('page_description','Consultez vos soldes par devise et retrouvez les mouvements enregistrés sur votre portefeuille.')
@section('page_actions')
<div class="finance-page__navigation" aria-label="Navigation financière">
    <a class="button button--secondary" href="{{ route('professional.revenues.index') }}">Mes revenus</a>
    <a class="button button--secondary" href="{{ route('professional.withdrawals') }}">Historique des retraits</a>
    @if($wallet && $wallet->status === 'active')
    <a class="button button--primary" href="{{ route('professional.withdrawals.create', ['currency' => $wallet->currency]) }}"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i> Demander un retrait</a>
    @endif
</div>
@endsection
@section('content')
<div class="finance-page">
@if(session('status'))<div class="alert alert--success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())
<div class="alert alert--error" role="alert">
    <strong>Vérifiez les critères de recherche.</strong>
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif

<section class="wallet-toolbar" aria-label="Sélection du portefeuille">
    @if($currencies->isNotEmpty())
    <form method="GET" action="{{ route('professional.wallet') }}" class="finance-filter-form">
        <label for="wallet-currency">Devise du portefeuille</label>
        <select id="wallet-currency" name="currency">
            @foreach($currencies as $availableCurrency)
            <option value="{{ $availableCurrency }}" @selected($availableCurrency === $currency)>{{ $availableCurrency }}</option>
            @endforeach
        </select>
        <button class="button button--secondary button--small" type="submit">Afficher</button>
    </form>
    @else
    <p class="finance-note">Aucun portefeuille n’est encore associé à votre compte.</p>
    @endif
</section>

@if(!$wallet)
<section class="data-card wallet-empty-state" aria-labelledby="wallet-empty-title">
    <div class="finance-empty">
        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
        <h3 id="wallet-empty-title">Votre portefeuille est en attente d’activité</h3>
        <p>Un portefeuille sera créé par le processus financier autorisé lorsqu’une opération nécessitera un compte dans cette devise. Cette page de consultation ne crée et ne modifie aucun solde.</p>
    </div>
</section>
@else
@if($wallet->status !== 'active')
<div class="alert alert--warning" role="status">
    <strong>Portefeuille {{ str_replace('_', ' ', $wallet->status) }}.</strong>
    <span>Certains mouvements peuvent être indisponibles. Contactez le support si vous pensez qu’il s’agit d’une erreur.</span>
</div>
@endif

<section class="wallet-balance-grid" aria-label="Soldes du portefeuille">
    <article class="finance-balance-card finance-balance-card--main">
        <span class="finance-card-label">Disponible</span>
        <strong>{{ \App\Support\MoneyFormatter::format((string) $wallet->available_balance) }} {{ $wallet->currency }}</strong>
        <small>Montant actuellement disponible pour une demande de retrait, sous réserve des contrôles serveur.</small>
    </article>
    <article class="finance-balance-card">
        <span class="finance-card-label">En attente</span>
        <strong>{{ \App\Support\MoneyFormatter::format((string) $wallet->pending_balance) }} {{ $wallet->currency }}</strong>
        <small>Revenus qui n’ont pas encore été libérés vers le solde disponible.</small>
    </article>
    <article class="finance-balance-card">
        <span class="finance-card-label">Bloqué / réservé</span>
        <strong>{{ \App\Support\MoneyFormatter::format((string) $wallet->locked_balance) }} {{ $wallet->currency }}</strong>
        <small>Montants réservés pour des opérations en cours, notamment des demandes de retrait.</small>
    </article>
</section>

<section class="data-card" aria-labelledby="wallet-transactions-title">
    <div class="data-card__header">
        <div><span class="eyebrow">MOUVEMENTS</span><h3 id="wallet-transactions-title">Historique du portefeuille</h3><p class="finance-note">Les résultats restent limités à ce portefeuille et à cette devise.</p></div>
        <span class="status-badge status-badge--neutral">{{ $wallet->currency }}</span>
    </div>

    <form method="GET" action="{{ route('professional.wallet') }}" class="finance-filter-form finance-filter-form--inline wallet-history-filters">
        <input type="hidden" name="currency" value="{{ $wallet->currency }}">
        <div>
            <label for="wallet-type">Type d’opération</label>
            <select id="wallet-type" name="type">
                <option value="">Tous les types</option>
                @foreach($transactionTypes as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ match($type->value) { 'earning' => 'Revenu', 'commission' => 'Commission', 'withdrawal' => 'Retrait', 'refund' => 'Remboursement', 'adjustment' => 'Ajustement', 'reversal' => 'Contre-écriture', default => ucfirst($type->value) } }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="wallet-direction">Sens</label>
            <select id="wallet-direction" name="direction">
                <option value="">Crédits et débits</option>
                @foreach($transactionDirections as $direction)
                <option value="{{ $direction->value }}" @selected(($filters['direction'] ?? '') === $direction->value)>{{ $direction->value === 'credit' ? 'Crédit' : 'Débit' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="wallet-transaction-id">N° de mouvement</label>
            <input id="wallet-transaction-id" name="transaction_id" type="number" min="1" step="1" value="{{ $filters['transaction_id'] ?? '' }}" placeholder="Ex. 125">
        </div>
        <div>
            <label for="wallet-date-from">Du</label>
            <input id="wallet-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label for="wallet-date-to">Au</label>
            <input id="wallet-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="wallet-filter-actions">
            <button class="button button--secondary button--small" type="submit">Filtrer</button>
            <a class="button button--ghost button--small" href="{{ route('professional.wallet', ['currency' => $wallet->currency]) }}">Réinitialiser</a>
        </div>
    </form>

    @if($transactions->isEmpty())
    <div class="finance-empty">
        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
        <h4>Aucun mouvement correspondant</h4>
        <p>Aucun mouvement ne correspond à ces critères. Essayez de modifier les filtres ou de réinitialiser la recherche.</p>
        @if(($filters['type'] ?? null) || ($filters['direction'] ?? null) || ($filters['transaction_id'] ?? null) || ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null))
        <a class="button button--secondary button--small" href="{{ route('professional.wallet', ['currency' => $wallet->currency]) }}">Effacer les filtres</a>
        @endif
    </div>
    @else
    <div class="finance-table-wrap">
        <table class="finance-table">
            <thead><tr><th>Date</th><th>Référence</th><th>Opération</th><th>Sens</th><th>Compartiment</th><th>Montant</th></tr></thead>
            <tbody>
            @foreach($transactions as $transaction)
            <tr>
                <td><time datetime="{{ $transaction->created_at?->toISOString() }}">{{ $transaction->created_at?->format('d/m/Y H:i') }}</time></td>
                <td><span class="wallet-transaction-reference">#{{ $transaction->id }}</span>@if($transaction->reference_id)<small class="wallet-reference-detail">Réf. liée : {{ class_basename((string) $transaction->reference_type) }} #{{ $transaction->reference_id }}</small>@endif</td>
                <td>{{ $transaction->description ?: match($transaction->type?->value) { 'earning' => 'Revenu', 'commission' => 'Commission', 'withdrawal' => 'Retrait', 'refund' => 'Remboursement', 'adjustment' => 'Ajustement', 'reversal' => 'Contre-écriture', default => 'Opération financière' } }}</td>
                <td><span class="finance-direction finance-direction--{{ $transaction->direction?->value }}">{{ match($transaction->direction?->value) { 'credit' => 'Crédit', 'debit' => 'Débit', default => 'Sens non déterminé' } }}</span></td>
                <td><span class="wallet-bucket-label wallet-bucket-label--{{ $transaction->balance_bucket ?? 'unknown' }}">{{ match($transaction->balance_bucket ?? 'unknown') { 'available' => 'Disponible', 'pending' => 'En attente', 'locked' => 'Bloqué / réservé', 'unknown' => 'Non déterminé (historique)', default => 'Non déterminé' } }}</span></td>
                <td class="finance-amount finance-amount--{{ $transaction->direction?->value }}">{{ $transaction->direction?->value === 'credit' ? '+' : ($transaction->direction?->value === 'debit' ? '−' : '') }}{{ \App\Support\MoneyFormatter::format((string) $transaction->amount) }} {{ $transaction->currency }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="finance-pagination">{{ $transactions->links() }}</div>
    @endif
    <p class="wallet-ledger-disclaimer"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Cet historique affiche les écritures présentes dans le registre actuel. Il ne constitue pas encore un relevé comptable certifié : certaines transitions de retrait et le rapprochement complet des remboursements doivent être consolidés dans les flux financiers dédiés.</p>
</section>
@endif
</div>
@endsection
