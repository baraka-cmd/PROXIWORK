@extends('layouts.professional')
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/professional/wallet-withdrawals.css','resources/js/pages/professional/wallet-withdrawals.js'])
@endunless
@endpush
@section('page_eyebrow','FINANCES')
@section('page_title','Mes retraits')
@section('page_description','Consultez vos demandes, les montants réservés et l’état de leur traitement.')
@section('page_actions')
<div class="finance-page__navigation" aria-label="Navigation financière">
    <a class="button button--secondary" href="{{ route('professional.wallet') }}">Mon portefeuille</a>
    <a class="button button--secondary" href="{{ route('professional.revenues.index') }}">Mes revenus</a>
    @if($canRequestWithdrawal)
    <a class="button button--primary" href="{{ route('professional.withdrawals.create') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Demander un retrait</a>
    @endif
</div>
@endsection
@section('content')
<div class="finance-page">
@if(session('status'))<div class="alert alert--success" role="status">{{ session('status') }}</div>@endif
@if(!$canRequestWithdrawal)
<div class="alert alert--warning" role="status">Aucun portefeuille actif ne permet actuellement de soumettre un retrait. Contactez le support si vous pensez qu’il s’agit d’une erreur.</div>
@endif

<section class="data-card" aria-labelledby="withdrawal-summary-title">
    <div class="data-card__header"><div><span class="eyebrow">SITUATION ACTUELLE</span><h3 id="withdrawal-summary-title">Résumé par devise</h3></div></div>
    @if($walletsByCurrency->isEmpty())
        <div class="finance-empty"><i class="fa-solid fa-wallet" aria-hidden="true"></i><h4>Aucun portefeuille disponible</h4><p>Les montants apparaîtront ici lorsqu’un portefeuille sera créé par un flux financier autorisé.</p></div>
    @else
        <div class="withdrawal-currency-grid">
        @foreach($walletsByCurrency as $currency => $wallet)
            @php($inProgress = $inProgressByCurrency->get($currency))
            @php($successful = $successfulCountsByCurrency->get($currency))
            <article class="withdrawal-currency-card">
                <div class="withdrawal-currency-card__header"><strong>{{ $currency }}</strong><span class="status-badge status-badge--neutral">{{ strtolower((string)$wallet->status) === 'active' ? 'Actif' : 'Non actif' }}</span></div>
                <dl>
                    <div><dt>Retirable actuellement</dt><dd>{{ \App\Support\MoneyFormatter::format((string)$wallet->available_balance) }} {{ $currency }}</dd></div>
                    <div><dt>Réservé / en cours</dt><dd>{{ \App\Support\MoneyFormatter::format((string)($inProgress->amount_total ?? '0.00')) }} {{ $currency }}</dd></div>
                    <div><dt>Demandes en cours</dt><dd>{{ (int)($inProgress->withdrawal_count ?? 0) }}</dd></div>
                    <div><dt>Retraits réussis — cumul historique</dt><dd>{{ (int)($successful->successful_count ?? 0) }}</dd></div>
                </dl>
            </article>
        @endforeach
        </div>
    @endif
    <p class="finance-note">Les indicateurs de retraits réussis sont cumulatifs depuis le début de l’historique. Les montants restent séparés par devise. Une demande en attente ou en vérification n’est pas un paiement exécuté.</p>
</section>

<div class="alert alert--warning" role="note">
    <strong>Traitement des paiements</strong>
    <p>Les demandes réservent les fonds, mais aucun fournisseur réel de versement n’est actuellement déclaré intégré. Mobile Money et virement sont enregistrés comme canaux demandés pour traitement manuel; un statut « Réussi » exige une référence de preuve vérifiée.</p>
</div>

<form method="GET" action="{{ route('professional.withdrawals') }}" class="finance-filter-form finance-filter-form--inline">
    <div><label for="withdrawal-status">Statut</label><select id="withdrawal-status" name="status"><option value="">Tous les statuts</option>@foreach(\App\Enums\WithdrawalStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
    <div><label for="withdrawal-currency">Devise</label><select id="withdrawal-currency" name="currency"><option value="">Toutes les devises</option>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>@endforeach</select></div>
    <div><label for="withdrawal-date-from">Du</label><input id="withdrawal-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
    <div><label for="withdrawal-date-to">Au</label><input id="withdrawal-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
    <div class="wallet-filter-actions"><button class="button button--secondary button--small" type="submit">Filtrer</button><a class="button button--secondary button--small" href="{{ route('professional.withdrawals') }}">Réinitialiser</a></div>
</form>
@if($errors->any())<div class="alert alert--error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="data-card" aria-labelledby="withdrawals-title">
    <div class="data-card__header"><div><span class="eyebrow">HISTORIQUE</span><h3 id="withdrawals-title">Demandes de retrait</h3></div><span class="status-badge status-badge--neutral">{{ $withdrawals->total() }} demande(s)</span></div>
    @if($withdrawals->isEmpty())
        <div class="finance-empty" role="status">
            <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
            @if(($filters['status'] ?? null) || ($filters['currency'] ?? null) || ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null))
                <h4>Aucun retrait ne correspond aux filtres</h4>
                <p>Modifiez les critères de recherche ou effacez-les pour retrouver l’historique complet.</p>
                <a class="button button--secondary button--small" href="{{ route('professional.withdrawals') }}">Effacer les filtres</a>
            @else
                <h4>Aucune demande de retrait pour le moment</h4>
                <p>Les demandes enregistrées apparaîtront ici avec leur référence, leur montant et leur statut réel.</p>
                @if($canRequestWithdrawal)<a class="button button--primary button--small" href="{{ route('professional.withdrawals.create') }}">Demander un retrait</a>@endif
            @endif
        </div>
    @else
        <div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Date de demande</th><th>Référence</th><th>Montant demandé</th><th>Canal demandé</th><th>Statut</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
        @foreach($withdrawals as $withdrawal)
            <tr>
                <td>@if($withdrawal->requested_at)<time datetime="{{ $withdrawal->requested_at->toISOString() }}">{{ $withdrawal->requested_at->format('d/m/Y H:i') }}</time>@else — @endif</td>
                <td><strong class="wallet-transaction-reference">WD-{{ str_pad((string)$withdrawal->getKey(), 8, '0', STR_PAD_LEFT) }}</strong></td>
                <td><strong>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</strong></td>
                <td>{{ $withdrawal->provider === 'mobile_money' ? 'Mobile Money — manuel' : ($withdrawal->provider === 'bank_transfer' ? 'Virement bancaire — manuel' : 'Autre canal') }}</td>
                <td><span class="withdrawal-status withdrawal-status--{{ $withdrawal->status->value }}">{{ $withdrawal->status->label() }}</span></td>
                <td><a class="button button--secondary button--small" href="{{ route('professional.withdrawals.show',$withdrawal) }}">Détails</a></td>
            </tr>
        @endforeach
        </tbody></table></div>
        <div class="finance-pagination">{{ $withdrawals->links() }}</div>
    @endif
</section>
</div>
@endsection
