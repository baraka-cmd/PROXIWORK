@extends('layouts.professional')
@section('page_eyebrow','FINANCES')
@section('page_title','Wallet')
@section('page_description','Consultez vos soldes, vos mouvements et l’argent actuellement disponible au retrait.')
@section('page_actions')
<a class="button button--primary" href="{{ route('professional.withdrawals.create', ['currency'=>$wallet->currency]) }}"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i> Demander un retrait</a>
@endsection
@section('content')
<div class="finance-page">
@if(session('status'))<div class="alert alert--success" role="status">{{ session('status') }}</div>@endif
<section class="wallet-toolbar" aria-label="Portefeuille">
<form method="GET" action="{{ route('professional.wallet') }}" class="finance-filter-form">
<label for="wallet-currency">Devise</label>
<select id="wallet-currency" name="currency" onchange="this.form.submit()">
@foreach($currencies as $currency)<option value="{{ $currency }}" @selected($currency === $wallet->currency)>{{ $currency }}</option>@endforeach
</select>
<noscript><button class="button button--secondary button--small" type="submit">Afficher</button></noscript>
</form>
</section>
<section class="wallet-balance-grid" aria-label="Soldes du portefeuille">
<article class="finance-balance-card finance-balance-card--main"><span class="finance-card-label">Disponible</span><strong>{{ number_format((float)$wallet->available_balance,2,',',' ') }} {{ $wallet->currency }}</strong><small>Montant pouvant être demandé en retrait.</small></article>
<article class="finance-balance-card"><span class="finance-card-label">En attente</span><strong>{{ number_format((float)$wallet->pending_balance,2,',',' ') }} {{ $wallet->currency }}</strong><small>Revenus crédités mais pas encore libérés.</small></article>
<article class="finance-balance-card"><span class="finance-card-label">Bloqué</span><strong>{{ number_format((float)$wallet->locked_balance,2,',',' ') }} {{ $wallet->currency }}</strong><small>Montant réservé pour des opérations en cours.</small></article>
</section>
<section class="data-card" aria-labelledby="wallet-transactions-title">
<div class="data-card__header"><div><span class="eyebrow">HISTORIQUE</span><h3 id="wallet-transactions-title">Mouvements du wallet</h3></div><span class="status-badge status-badge--neutral">{{ $wallet->currency }}</span></div>
@if($transactions->isEmpty())
<div class="finance-empty"><i class="fa-solid fa-receipt" aria-hidden="true"></i><h4>Aucun mouvement</h4><p>Les revenus et retraits apparaîtront ici au fur et à mesure de votre activité.</p></div>
@else
<div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Date</th><th>Opération</th><th>Sens</th><th>Montant</th><th>Solde après</th></tr></thead><tbody>
@foreach($transactions as $transaction)
<tr><td>{{ $transaction->created_at?->format('d/m/Y H:i') }}</td><td>{{ $transaction->description ?: str_replace('_',' ',$transaction->type?->value ?? 'opération') }}</td><td><span class="finance-direction finance-direction--{{ $transaction->direction?->value }}">{{ $transaction->direction?->value === 'credit' ? 'Crédit' : 'Débit' }}</span></td><td class="finance-amount finance-amount--{{ $transaction->direction?->value }}">{{ $transaction->direction?->value === 'credit' ? '+' : '-' }}{{ number_format((float)$transaction->amount,2,',',' ') }} {{ $transaction->currency }}</td><td>{{ number_format((float)$transaction->balance_after,2,',',' ') }} {{ $transaction->currency }}</td></tr>
@endforeach
</tbody></table></div><div class="finance-pagination">{{ $transactions->links() }}</div>
@endif
</section>
</div>
@endsection
