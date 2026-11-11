@extends('layouts.client')

@section('title', 'Détail du paiement')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
@endpush
@push('scripts')
    @vite('resources/js/pages/client/orders-payments.js')
@endpush

@php
    $statusLabels = [
        'initiated' => 'Initialisé',
        'pending' => 'En attente',
        'processing' => 'En traitement / vérification',
        'succeeded' => 'Réussite simulée',
        'failed' => 'Échec simulé',
        'cancelled' => 'Annulé',
        'expired' => 'Expiré',
        'refunded' => 'Remboursé (simulation)',
    ];
    $transactionLabels = [
        'initiated' => 'Tentative créée',
        'pending' => 'En attente',
        'processing' => 'En traitement',
        'unknown' => 'Résultat inconnu — vérification nécessaire',
        'succeeded' => 'Réussite simulée',
        'failed' => 'Échec simulé',
        'cancelled' => 'Annulée',
        'expired' => 'Expirée',
        'refunded' => 'Remboursée (simulation)',
    ];
    $eventLabels = [
        'attempt.created' => 'Tentative créée',
        'gateway.result' => 'Résultat de la passerelle enregistré',
        'gateway.amount_mismatch' => 'Anomalie de montant ou de devise',
        'gateway.outcome_unknown' => 'Résultat incertain — vérification requise',
        'payment.confirmed' => 'Paiement confirmé dans la simulation',
        'payment.confirmed.order_incompatible' => 'Paiement confirmé — rapprochement requis avec la commande',
        'payment.status_changed' => 'État financier mis à jour',
        'refund.requested' => 'Demande de remboursement enregistrée',
        'webhook.result' => 'Événement externe traité',
        'webhook.final_state_rechecked' => 'Événement externe recontrôlé',
    ];
    $refundLabels = [
        'requested' => 'Demande enregistrée — examen requis',
        'processing' => 'Examen / traitement en cours',
        'succeeded' => 'Remboursement confirmé (simulation)',
        'failed' => 'Échec du remboursement',
        'rejected' => 'Demande refusée',
        'cancelled' => 'Demande annulée',
    ];
    $hasOpenRefund = $payment->refunds->contains(fn ($refund) => $refund->status->isOpen());
    $formatFinancialStatus = fn ($value) => $statusLabels[$value] ?? $transactionLabels[$value] ?? $refundLabels[$value] ?? 'État mis à jour';
@endphp

<div class="client-module client-payment-detail">
    <header class="client-module__header payment-detail-header">
        <div>
            <p class="client-module__eyebrow">DÉTAIL FINANCIER</p>
            <h1>PAY-{{ str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT) }}</h1>
            <p>Commande {{ $payment->order?->order_number ?? '#'.$payment->order_id }}</p>
        </div>
        <span class="status-badge status-badge--{{ $payment->status->value }}">{{ $statusLabels[$payment->status->value] ?? 'État à vérifier' }}</span>
    </header>

    <aside class="payment-simulation-notice" role="note">
        <i class="fa-solid fa-flask" aria-hidden="true"></i>
        <p><strong>Mode simulation.</strong> Ce suivi et les reçus associés ne constituent pas une preuve de transfert Mobile Money, bancaire ou par carte réel.</p>
    </aside>

    @if(session('success'))<div class="payment-feedback payment-feedback--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong>Information enregistrée</strong><p>{{ session('success') }}</p></div></div>@endif
    @if($errors->any())
        <div class="payment-error-summary" role="alert" tabindex="-1" data-error-summary>
            <h2>Une vérification est nécessaire</h2>
            <ul>
                @foreach($errors->all() as $error)
                    <li><a href="{{ $errors->has('reason') ? '#refund-reason' : '#refund-requests' }}">{{ $error }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="client-panel payment-detail-summary">
        <div class="payment-detail-summary__heading">
            <div><span class="client-module__eyebrow">MONTANT ENREGISTRÉ</span><p class="payment-amount">{{ number_format((float) $payment->amount, 2, ',', ' ') }} <span>{{ $payment->currency }}</span></p></div>
            <a class="btn btn-secondary" href="{{ route('client.payments.show', $payment) }}"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Vérifier le statut</a>
        </div>
        <dl class="client-definition-list">
            <div><dt>Référence paiement</dt><dd>PAY-{{ str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT) }}</dd></div>
            <div><dt>Commande</dt><dd>{{ $payment->order?->order_number ?? '#'.$payment->order_id }}</dd></div>
            <div><dt>Service</dt><dd>{{ $payment->order?->items?->first()?->service_title ?? 'Prestation de service' }}</dd></div>
            <div><dt>Moyen</dt><dd>{{ str_replace('_', ' ', $payment->method->value) }} (simulé)</dd></div>
            <div><dt>Passerelle</dt><dd>Simulateur PROXIWORK</dd></div>
            <div><dt>Créé le</dt><dd>{{ optional($payment->created_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            <div><dt>Confirmé le</dt><dd>{{ optional($payment->paid_at)->format('d/m/Y à H:i') ?? 'Non confirmé' }}</dd></div>
        </dl>
    </section>

    @if($payment->status === \App\Enums\PaymentStatus::SUCCEEDED)
        <section class="client-panel payment-receipt-panel">
            <div><span class="payment-panel-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span><h2>Reçu de simulation</h2><p>Le reçu est lié à la confirmation persistée et peut être vérifié à partir des données conservées par PROXIWORK.</p></div>
            @if($receipt)
                <dl class="client-definition-list">
                    <div><dt>Numéro du reçu</dt><dd>{{ $receipt->receipt_number }}</dd></div>
                    <div><dt>Émis le</dt><dd>{{ optional($receipt->issued_at)->format('d/m/Y à H:i') }}</dd></div>
                    <div><dt>Empreinte SHA-256</dt><dd class="payment-hash">{{ $receipt->content_hash }}</dd></div>
                </dl>
                <a class="btn btn-primary" href="{{ route('client.payments.receipt', $payment) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Consulter / imprimer le reçu</a>
            @else
                <p class="payment-muted">Aucune transaction confirmée ne permet encore d’émettre un reçu.</p>
            @endif
        </section>
    @endif

    <section class="client-panel">
        <div class="payment-section-heading"><div><h2>Historique des tentatives</h2><p>Chaque tentative reste visible même si une autre tentative est lancée plus tard.</p></div></div>
        @forelse($payment->transactions as $transaction)
            <article class="payment-attempt">
                @php($attemptReference = $transaction->provider === 'fake' ? 'SIM-TXN-'.str_pad((string) $transaction->id, 8, '0', STR_PAD_LEFT) : ($transaction->provider_reference ?? $transaction->provider_transaction_id ?? 'Tentative #'.$transaction->id))
                <div class="payment-attempt__top">
                    <div><strong>{{ $attemptReference }}</strong><small>{{ optional($transaction->created_at)->format('d/m/Y à H:i') ?? 'Date non disponible' }}</small></div>
                    <span class="status-badge status-badge--{{ $transaction->status->value }}">{{ $transactionLabels[$transaction->status->value] ?? 'État de tentative à vérifier' }}</span>
                </div>
                <dl class="client-definition-list">
                    <div><dt>Montant attendu</dt><dd>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</dd></div>
                    <div><dt>Initialisée</dt><dd>{{ optional($transaction->initiated_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                    <div><dt>Traitée</dt><dd>{{ optional($transaction->processed_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                        @if($transaction->status->value === 'unknown')<div><dt>À faire</dt><dd>Vérifiez cette même opération plus tard. Ne concluez pas à un échec et ne lancez pas de doublon.</dd></div>@endif
                </dl>
                @if($transaction->status->value === 'failed')
                    <p class="payment-attempt__safe-message">Cette tentative n’a pas abouti dans le simulateur. Consultez le statut enregistré avant de décider de la suite.</p>
                @elseif($transaction->status->value === 'unknown')
                    <div class="payment-uncertain-notice" role="note"><strong>Résultat incertain</strong><p>Le résultat n’est pas confirmé. Revenez vérifier cette même opération et n’en lancez pas une autre à l’aveugle.</p></div>
                @endif
                @if($transaction->failure_code)
                    <details class="payment-technical-details"><summary>Informations techniques</summary><dl class="client-definition-list"><div><dt>Code de résultat</dt><dd class="payment-hash">{{ $transaction->failure_code }}</dd></div></dl></details>
                @endif
            </article>
        @empty
            <p class="payment-muted">Aucune tentative n’est associée à ce paiement.</p>
        @endforelse
    </section>

    <section class="client-panel">
        <div class="payment-section-heading"><div><h2>Journal des événements</h2><p>Historique enregistré côté serveur. Une actualisation de la page ne crée pas une nouvelle opération financière.</p></div></div>
        @forelse($payment->events as $event)
            <article class="payment-event-item">
                <span class="payment-event-item__marker" aria-hidden="true"><i class="fa-solid fa-circle"></i></span>
                <div class="payment-event-item__body">
                    <strong>{{ $eventLabels[$event->event_type] ?? 'État mis à jour' }}</strong>
                    <p>@if($event->from_status) {{ $formatFinancialStatus($event->from_status) }} → @endif{{ $formatFinancialStatus($event->to_status) }}</p>
                    <small>{{ optional($event->occurred_at)->format('d/m/Y à H:i:s') ?? 'Date non disponible' }}</small>
                </div>
            </article>
        @empty
            <p class="payment-muted">Aucun événement historique n’est enregistré pour ce paiement.</p>
        @endforelse
    </section>

    <section class="client-panel payment-refunds-panel" id="refund-requests">
        <div class="payment-section-heading"><div><h2>Demandes de remboursement</h2><p>Une demande n’est pas un remboursement effectué. Seule une confirmation par un processus autorisé pourra changer cet état.</p></div></div>
        @forelse($payment->refunds as $refund)
            <article class="payment-refund-item payment-refund-item--{{ $refund->status->value }}">
                <div class="payment-refund-item__identity"><strong>{{ $refund->reference }}</strong><small>Demande enregistrée le {{ optional($refund->requested_at)->format('d/m/Y à H:i') ?? 'Date non disponible' }}</small></div>
                <div class="payment-refund-item__amount"><strong>{{ number_format((float) $refund->amount, 2, ',', ' ') }} {{ $refund->currency }}</strong><span class="status-badge status-badge--{{ $refund->status->value }}">{{ $refundLabels[$refund->status->value] ?? 'État de remboursement à vérifier' }}</span></div>
                @if($refund->status->value === 'requested')<p class="payment-refund-item__state">Votre demande est enregistrée et attend son examen. Aucun remboursement n’a encore été exécuté.</p>
                @elseif($refund->status->value === 'processing')<p class="payment-refund-item__state">La demande est en cours d’examen ou de traitement. Cela ne confirme pas encore un remboursement.</p>
                @elseif($refund->status->value === 'succeeded')<p class="payment-refund-item__state">Remboursement confirmé dans la simulation PROXIWORK ; aucun transfert réel n’a été effectué.</p>
                @endif
                <p class="payment-refund-item__reason"><strong>Motif fourni</strong>{{ $refund->reason }}</p>
            </article>
        @empty
            <p class="payment-muted">Aucune demande de remboursement enregistrée.</p>
        @endforelse

        @if($payment->status === \App\Enums\PaymentStatus::SUCCEEDED && !$hasOpenRefund)
            <form method="POST" action="{{ route('client.payments.refunds.store', $payment) }}" class="payment-refund-form">
                @csrf
                <div class="payment-refund-form__intro">
                    <span class="payment-panel-icon" aria-hidden="true"><i class="fa-solid fa-rotate-left"></i></span>
                    <div><h3>Demander l’examen d’un remboursement</h3><p>Cette demande sera examinée. Son envoi ne signifie pas qu’elle est acceptée ou que l’argent a été remboursé.</p></div>
                </div>
                <div class="payment-refund-context" aria-label="Paiement concerné par la demande">
                    <div><span>Référence</span><strong>PAY-{{ str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT) }}</strong></div>
                    <div><span>Montant enregistré</span><strong>{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</strong></div>
                    <div><span>Date de création</span><strong>{{ optional($payment->created_at)->format('d/m/Y à H:i') ?? 'Date non disponible' }}</strong></div>
                    <div><span>État</span><strong>{{ $statusLabels[$payment->status->value] ?? 'État à vérifier' }}</strong></div>
                </div>
                <div class="payment-refund-field">
                    <label for="refund-reason">Pourquoi demandez-vous un remboursement ?</label>
                    <p id="refund-reason-help">Décrivez ce qui s’est passé et pourquoi vous demandez un examen. Saisissez entre 10 et 1 000 caractères.</p>
                    @if($errors->has('reason'))<p class="payment-field-error" id="refund-reason-error">{{ $errors->first('reason') }}</p>@endif
                    <textarea id="refund-reason" name="reason" rows="5" minlength="10" maxlength="1000" required data-char-count aria-describedby="refund-reason-help refund-reason-count @if($errors->has('reason'))refund-reason-error @endif" @if($errors->has('reason')) aria-invalid="true" @endif>{{ old('reason') }}</textarea>
                    <small class="payment-character-count" id="refund-reason-count" data-char-counter data-count-for="refund-reason" aria-live="polite">0 / 1 000 caractères</small>
                </div>
                <div class="payment-refund-form__actions"><p><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Le montant demandé est calculé par PROXIWORK. Vous n’avez pas à saisir de montant.</p><button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Envoyer la demande</button></div>
            </form>
        @elseif($hasOpenRefund)
            <div class="payment-open-refund-note" role="status"><span class="payment-panel-icon" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span><div><strong>Une demande est déjà en cours</strong><p>La demande ouverte ci-dessus doit être examinée avant toute nouvelle demande pour ce paiement. Aucun remboursement n’est confirmé à ce stade.</p></div></div>
        @endif
    </section>

    <footer class="payment-detail-footer">
        <a class="btn btn-secondary" href="{{ route('client.payments.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à mes paiements</a>
        @if($payment->status !== \App\Enums\PaymentStatus::SUCCEEDED && $payment->order?->status === \App\Enums\OrderStatus::PENDING_PAYMENT && $payment->status->isFinal())
            <a class="btn btn-primary" href="{{ route('client.orders.payment', $payment->order) }}">Nouvelle tentative</a>
        @endif
        @if($payment->order)<a class="payment-text-link" href="{{ route('client.orders.show', $payment->order) }}">Voir la commande</a>@endif
    </footer>
</div>
@endsection
