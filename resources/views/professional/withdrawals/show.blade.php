@extends('layouts.professional')
@push('head')
@unless(app()->environment('testing'))
@vite(['resources/css/pages/professional/wallet-withdrawals.css','resources/js/pages/professional/wallet-withdrawals.js'])
@endunless
@endpush
@section('page_eyebrow','FINANCES')
@section('page_title','Détail du retrait')
@section('page_description','Consultez la demande, les preuves enregistrées et les transitions de statut.')
@section('page_actions')
<a class="button button--secondary" href="{{ route('professional.withdrawals') }}">Retour aux retraits</a>
@endsection
@section('content')
<div class="finance-page finance-page--narrow">
    @if(session('status'))<div class="alert alert--success" role="status">{{ session('status') }}</div>@endif
    <section class="data-card">
        <div class="data-card__header">
            <div><span class="eyebrow">RÉFÉRENCE WD-{{ str_pad((string)$withdrawal->getKey(), 8, '0', STR_PAD_LEFT) }}</span><h3>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</h3></div>
            <span class="withdrawal-status withdrawal-status--{{ $withdrawal->status->value }}">{{ $withdrawal->status->label() }}</span>
        </div>
        <dl class="finance-details">
            <div><dt>Montant demandé</dt><dd>{{ \App\Support\MoneyFormatter::format((string)$withdrawal->amount) }} {{ $withdrawal->currency }}</dd></div>
            <div><dt>Frais enregistrés</dt><dd>{{ $withdrawal->fee_amount !== null ? \App\Support\MoneyFormatter::format((string)$withdrawal->fee_amount).' '.$withdrawal->currency : 'Non configurés' }}</dd></div>
            <div><dt>Montant net enregistré</dt><dd>{{ $withdrawal->net_amount !== null ? \App\Support\MoneyFormatter::format((string)$withdrawal->net_amount).' '.$withdrawal->currency : 'Non estimé — barème de frais absent' }}</dd></div>
            <div><dt>Moyen demandé</dt><dd>{{ $withdrawal->provider === 'mobile_money' ? 'Mobile Money — traitement manuel' : ($withdrawal->provider === 'bank_transfer' ? 'Virement bancaire — traitement manuel' : 'Autre canal') }}</dd></div>
            <div><dt>Destination masquée</dt><dd><span class="masked-destination">{{ $withdrawal->maskedDestination() }}</span></dd></div>
            <div><dt>Soumis le</dt><dd>{{ $withdrawal->requested_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            @if($withdrawal->processing_at)<div><dt>Traitement commencé</dt><dd>{{ $withdrawal->processing_at->format('d/m/Y H:i:s') }}</dd></div>@endif
            @if($withdrawal->completed_at)<div><dt>Terminé le</dt><dd>{{ $withdrawal->completed_at->format('d/m/Y H:i:s') }}</dd></div>@endif
            @if($withdrawal->provider_transaction_id)<div><dt>Référence de preuve enregistrée</dt><dd>{{ $withdrawal->provider_transaction_id }}</dd></div>@endif
        </dl>
        @if($withdrawal->status === \App\Enums\WithdrawalStatus::REQUESTED)
            <div class="alert alert--warning" role="status"><strong>Demande enregistrée, paiement non confirmé.</strong><p>Le montant est réservé. Vous pouvez annuler la demande tant que le traitement n’a pas commencé.</p></div>
        @elseif($withdrawal->status === \App\Enums\WithdrawalStatus::PROCESSING)
            <div class="alert alert--warning" role="status"><strong>Traitement en cours.</strong><p>Le statut sera mis à jour après une confirmation fiable. Ne soumettez pas un second retrait pour remplacer cette demande.</p></div>
        @elseif($withdrawal->status === \App\Enums\WithdrawalStatus::REVIEW_REQUIRED)
            <div class="alert alert--warning" role="alert"><strong>Résultat à vérifier.</strong><p>{{ $withdrawal->review_reason ?: 'Le résultat du transfert n’est pas encore déterminé.' }} Les fonds restent bloqués jusqu’au rapprochement; aucun nouveau transfert ne doit être déclenché automatiquement.</p></div>
        @elseif($withdrawal->status === \App\Enums\WithdrawalStatus::FAILED)
            <div class="alert alert--error" role="alert"><strong>Le retrait a échoué ou a été refusé.</strong><p>{{ $withdrawal->failure_message ?: $withdrawal->review_reason ?: 'Le montant réservé a été libéré après confirmation de l’échec.' }}</p></div>
        @elseif($withdrawal->status === \App\Enums\WithdrawalStatus::CANCELLED)
            <div class="alert alert--neutral" role="status">La demande a été annulée avant le traitement. La réservation a été libérée.</div>
        @elseif($withdrawal->status === \App\Enums\WithdrawalStatus::SUCCEEDED)
            <div class="alert alert--success" role="status"><strong>Retrait confirmé.</strong><p>La référence de preuve enregistrée est conservée pour le rapprochement financier.</p></div>
        @endif
        @if($withdrawal->status === \App\Enums\WithdrawalStatus::REQUESTED)
            <form method="POST" action="{{ route('professional.withdrawals.cancel', $withdrawal) }}" class="withdrawal-cancel-form">
                @csrf
                <p>Annuler libérera la réservation une seule fois. Cette action est disponible uniquement avant le début du traitement.</p>
                <button class="button button--danger" type="submit"><i class="fa-solid fa-ban" aria-hidden="true"></i> Annuler cette demande</button>
            </form>
        @endif
    </section>

    <section class="data-card" aria-labelledby="withdrawal-history-title">
        <div class="data-card__header"><div><span class="eyebrow">TRAÇABILITÉ</span><h3 id="withdrawal-history-title">Historique des statuts</h3></div></div>
        @if($withdrawal->statusHistories->isEmpty())
            <p class="finance-note">Aucun événement d’historique n’est disponible pour cette ancienne demande.</p>
        @else
            <ol class="withdrawal-timeline withdrawal-timeline--audit">
            @foreach($withdrawal->statusHistories as $history)
                <li class="is-complete">
                    <span><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                    <div>
                        <strong>{{ $history->to_status?->label() ?? (string)$history->to_status }}</strong>
                        <small>{{ $history->created_at?->format('d/m/Y H:i:s') }} · {{ $history->actor_type === 'admin' ? 'Équipe financière' : ($history->actor_type === 'professional' ? 'Professionnel' : 'Système') }}</small>
                        @if($history->reason)<p>{{ $history->reason }}</p>@endif
                    </div>
                </li>
            @endforeach
            </ol>
        @endif
    </section>
</div>
@endsection
