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
        @elseif(in_array($withdrawal->status, [\App\Enums\WithdrawalStatus::FAILED, \App\Enums\WithdrawalStatus::REJECTED], true))
            <div class="alert alert--error" role="alert"><strong>{{ $withdrawal->status === \App\Enums\WithdrawalStatus::REJECTED ? 'La demande a été refusée.' : 'Le retrait a échoué.' }}</strong><p>{{ $withdrawal->failure_message ?: $withdrawal->review_reason ?: 'La réservation a été libérée après confirmation du résultat.' }}</p></div>
        @elseif($withdrawal->status === \App\Enums\WithdrawalStatus::CANCELLED)
            <div class="alert alert--neutral" role="status">La demande a été annulée avant le traitement. La réservation a été libérée.</div>
        @elseif($withdrawal->status === \App\Enums\WithdrawalStatus::SUCCEEDED)
            <div class="alert alert--success" role="status"><strong>Retrait confirmé.</strong><p>La référence de preuve enregistrée est conservée pour le rapprochement financier.</p></div>
        @endif
        @if($withdrawal->status === \App\Enums\WithdrawalStatus::REQUESTED)
            <form method="POST" action="{{ route('professional.withdrawals.cancel', $withdrawal) }}" class="withdrawal-cancel-form" data-withdrawal-cancel>
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

    @if($supportTickets->isNotEmpty())
        <section class="data-card" aria-labelledby="withdrawal-support-history-title">
            <div class="data-card__header"><div><span class="eyebrow">ASSISTANCE</span><h3 id="withdrawal-support-history-title">Conversation avec le support</h3></div></div>
            @foreach($supportTickets as $ticket)
                <article class="withdrawal-support-ticket">
                    <div class="withdrawal-support-ticket__header"><strong>Ticket #{{ $ticket->getKey() }} — {{ $ticket->subject }}</strong><span class="status-badge status-badge--neutral">{{ str_replace('_', ' ', $ticket->status->value) }}</span></div>
                    <div class="withdrawal-support-messages">
                        @foreach($ticket->messages as $message)
                            <div class="withdrawal-support-message {{ (int)$message->sender_id === (int)auth()->id() ? 'withdrawal-support-message--own' : '' }}">
                                <strong>{{ (int)$message->sender_id === (int)auth()->id() ? 'Vous' : ($message->sender?->name ?? 'Support') }}</strong>
                                <small>{{ $message->created_at?->format('d/m/Y H:i') }}</small>
                                <p>{{ $message->body }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if($ticket->status->value !== 'closed')
                        <form method="POST" action="{{ route('professional.withdrawals.support.message', [$withdrawal, $ticket]) }}" class="finance-form withdrawal-support-reply">
                            @csrf
                            <div class="form-field"><label for="support-reply-{{ $ticket->getKey() }}">Votre réponse</label><textarea id="support-reply-{{ $ticket->getKey() }}" name="body" rows="3" minlength="10" maxlength="10000" required></textarea></div>
                            <button class="button button--secondary button--small" type="submit">Répondre au ticket</button>
                        </form>
                    @else
                        <p class="finance-note">Ce ticket est fermé. Si le problème persiste, vous pouvez ouvrir un nouveau signalement.</p>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    <section class="data-card" aria-labelledby="withdrawal-support-title">
        <div class="data-card__header"><div><span class="eyebrow">ASSISTANCE</span><h3 id="withdrawal-support-title">Signaler un problème</h3></div></div>
        <p class="finance-note">Le ticket sera lié à cette demande de retrait pour permettre au support de retrouver son statut et son historique. N’inscrivez pas de mot de passe, code PIN ou autre secret de paiement dans votre message.</p>
        @if($errors->has('body'))<div class="alert alert--error" role="alert">{{ $errors->first('body') }}</div>@endif
        <form method="POST" action="{{ route('professional.withdrawals.report', $withdrawal) }}" class="finance-form">
            @csrf
            <div class="form-field"><label for="withdrawal-problem-body">Décrivez le problème</label><textarea id="withdrawal-problem-body" name="body" rows="4" minlength="10" maxlength="10000" required>{{ old('body') }}</textarea>@error('body')<small class="field-error">{{ $message }}</small>@enderror</div>
            <button class="button button--secondary" type="submit"><i class="fa-solid fa-life-ring" aria-hidden="true"></i> Envoyer au support</button>
        </form>
    </section>
</div>
@endsection
