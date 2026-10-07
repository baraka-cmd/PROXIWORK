@extends('layouts.professional')
@section('page_eyebrow','FINANCES')
@section('page_title','Retraits')
@section('page_description','Suivez toutes vos demandes de retrait et leur état de traitement.')
@section('page_actions')
<a class="button button--primary" href="{{ route('professional.withdrawals.create') }}"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nouveau retrait</a>
@endsection
@section('content')
<div class="finance-page">
<form method="GET" action="{{ route('professional.withdrawals') }}" class="finance-filter-form finance-filter-form--inline">
<div><label for="withdrawal-status">Statut</label><select id="withdrawal-status" name="status"><option value="">Tous</option>@foreach(\App\Enums\WithdrawalStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></div>
<div><label for="withdrawal-currency">Devise</label><select id="withdrawal-currency" name="currency"><option value="">Toutes</option>@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(strtoupper((string)request('currency')) === $currency)>{{ $currency }}</option>@endforeach</select></div>
<button class="button button--secondary button--small" type="submit">Filtrer</button>
</form>
<section class="data-card" aria-labelledby="withdrawals-title"><div class="data-card__header"><div><span class="eyebrow">HISTORIQUE</span><h3 id="withdrawals-title">Demandes de retrait</h3></div><span class="status-badge status-badge--neutral">{{ $withdrawals->total() }} demande(s)</span></div>
@if($withdrawals->isEmpty())
<div class="finance-empty"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i><h4>Aucun retrait</h4><p>Vous n’avez encore enregistré aucune demande de retrait.</p></div>
@else
<div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Date</th><th>Montant</th><th>Canal</th><th>Statut</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
@foreach($withdrawals as $withdrawal)<tr><td>{{ $withdrawal->requested_at?->format('d/m/Y H:i') }}</td><td><strong>{{ number_format((float)$withdrawal->amount,2,',',' ') }} {{ $withdrawal->currency }}</strong></td><td>{{ str_replace('_',' ',$withdrawal->provider) }}</td><td><span class="withdrawal-status withdrawal-status--{{ $withdrawal->status->value }}">{{ ucfirst($withdrawal->status->value) }}</span></td><td><a class="button button--secondary button--small" href="{{ route('professional.withdrawals.show',$withdrawal) }}">Détails</a></td></tr>@endforeach
</tbody></table></div><div class="finance-pagination">{{ $withdrawals->links() }}</div>
@endif
</section>
</div>
@endsection
