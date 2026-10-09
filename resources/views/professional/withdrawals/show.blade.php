@extends('layouts.professional')
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/professional/wallet-withdrawals.css','resources/js/pages/professional/wallet-withdrawals.js'])
@endunless
@endpush
@section('page_eyebrow','FINANCES')
@section('page_title','Détail du retrait')
@section('page_description','État et historique de votre demande de retrait.')
@section('page_actions')<a class="button button--secondary" href="{{ route('professional.withdrawals') }}">Retour aux retraits</a>@endsection
@section('content')
<div class="finance-page finance-page--narrow">
@if(session('status'))<div class="alert alert--success" role="status">{{ session('status') }}</div>@endif
<section class="data-card"><div class="data-card__header"><div><span class="eyebrow">RETRAIT #{{ $withdrawal->id }}</span><h3>{{ number_format((float)$withdrawal->amount,2,',',' ') }} {{ $withdrawal->currency }}</h3></div><span class="withdrawal-status withdrawal-status--{{ $withdrawal->status->value }}">{{ ucfirst($withdrawal->status->value) }}</span></div>
<dl class="finance-details"><div><dt>Canal</dt><dd>{{ str_replace('_',' ',$withdrawal->provider) }}</dd></div><div><dt>Destination</dt><dd>{{ $withdrawal->destination }}</dd></div><div><dt>Demandé le</dt><dd>{{ $withdrawal->requested_at?->format('d/m/Y H:i') }}</dd></div>@if($withdrawal->processing_at)<div><dt>Traitement commencé</dt><dd>{{ $withdrawal->processing_at->format('d/m/Y H:i') }}</dd></div>@endif @if($withdrawal->completed_at)<div><dt>Terminé le</dt><dd>{{ $withdrawal->completed_at->format('d/m/Y H:i') }}</dd></div>@endif @if($withdrawal->provider_transaction_id)<div><dt>Référence fournisseur</dt><dd>{{ $withdrawal->provider_transaction_id }}</dd></div>@endif</dl>
@if($withdrawal->status->value === 'failed')<div class="alert alert--error" role="alert"><strong>Le retrait n’a pas abouti.</strong>@if($withdrawal->failure_message)<p>{{ $withdrawal->failure_message }}</p>@endif</div>@endif
</section>
<section class="data-card" aria-labelledby="withdrawal-timeline-title"><div class="data-card__header"><div><span class="eyebrow">SUIVI</span><h3 id="withdrawal-timeline-title">Progression</h3></div></div>
<ol class="withdrawal-timeline"><li class="is-complete"><span>1</span><div><strong>Demande enregistrée</strong><small>{{ $withdrawal->requested_at?->format('d/m/Y H:i') }}</small></div></li><li class="{{ in_array($withdrawal->status->value,['processing','succeeded'],true) ? 'is-complete' : '' }}"><span>2</span><div><strong>Traitement</strong><small>{{ $withdrawal->processing_at?->format('d/m/Y H:i') ?: 'En attente' }}</small></div></li><li class="{{ $withdrawal->status->value === 'succeeded' ? 'is-complete' : '' }}"><span>3</span><div><strong>Retrait terminé</strong><small>{{ $withdrawal->completed_at?->format('d/m/Y H:i') ?: 'En attente' }}</small></div></li></ol>
@if($withdrawal->status->value === 'failed')<p class="finance-note">Le montant réservé a été réintégré dans le solde disponible par le service financier.</p>@elseif($withdrawal->status->value === 'requested')<p class="finance-note">Le montant est actuellement bloqué. Aucun fournisseur externe n’est déclaré comme exécutant tant que son intégration n’est pas activée.</p>@endif
</section>
</div>
@endsection
