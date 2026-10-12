@extends('layouts.client')

@section('title', 'Détail du paiement')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
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
        'refunded' => 'Remboursé',
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
        'refunded' => 'Remboursée',
    ];
    $refundLabels = [
        'requested' => 'Demande enregistrée — examen requis',
        'processing' => 'Examen / traitement en cours',
        'succeeded' => 'Remboursement confirmé',
        'failed' => 'Échec du remboursement',
        'rejected' => 'Demande refusée',
        'cancelled' => 'Demande annulée',
    ];
    $hasOpenRefund = $payment->refunds->contains(fn ($refund) => $refund->status->isOpen());
@endphp

<div class="client-module client-payment-detail">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">DÉTAIL FINANCIER</p>
            <h1>PAY-{{ str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT) }}</h1>
            <p>Commande {{ $payment->order?->order_number ?? '#'.$payment->order_id }}</p>
        </div>
        <span class="status-badge status-badge--{{ $payment->status->value }}">{{ $statusLabels[$payment->status->value] ?? $payment->status->value }}</span>
    </header>

    <aside class="payment-simulation-notice" role="note">
        <i class="fa-solid fa-flask" aria-hidden="true"></i>
        <p><strong>Mode simulation.</strong> Ce suivi et les reçus associés ne constituent pas une preuve de transfert Mobile Money, bancaire ou par carte réel.</p>
    </aside>

    @if(session('success'))<div class="payment-feedback payment-feedback--success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="payment-feedback payment-feedback--error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

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
                <div class="payment-attempt__top">
                    <div><strong>{{ $transaction->provider_reference ?? $transaction->provider_transaction_id ?? 'Tentative #'.$transaction->id }}</strong><small>{{ optional($transaction->created_at)->format('d/m/Y à H:i') ?? 'Date non disponible' }}</small></div>
                    <span class="status-badge status-badge--{{ $transaction->status->value }}">{{ $transactionLabels[$transaction->status->value] ?? $transaction->status->value }}</span>
                </div>
                <dl class="client-definition-list">
                    <div><dt>Montant attendu</dt><dd>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</dd></div>
                    <div><dt>Initialisée</dt><dd>{{ optional($transaction->initiated_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                    <div><dt>Traitée</dt><dd>{{ optional($transaction->processed_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                    @if($transaction->failure_code)<div><dt>Code de résultat</dt><dd>{{ $transaction->failure_code }}</dd></div>@endif
                    @if($transaction->status->value === 'unknown')<div><dt>À faire</dt><dd>Revenir vérifier cette même opération. Ne pas conclure à un échec ni lancer un doublon.</dd></div>@endif
                </dl>
                @if($transaction->failure_message)<p class="payment-muted">{{ $transaction->failure_message }}</p>@endif
            </article>
        @empty
            <p class="payment-muted">Aucune tentative n’est associée à ce paiement.</p>
        @endforelse
    </section>

    <section class="client-panel">
        <div class="payment-section-heading"><div><h2>Demandes de remboursement</h2><p>Une demande n’est pas un remboursement effectué. Seule une confirmation par un processus autorisé pourra changer cet état.</p></div></div>
        @forelse($payment->refunds as $refund)
            <article class="payment-refund-item">
                <div><strong>{{ $refund->reference }}</strong><small>Demandée le {{ optional($refund->requested_at)->format('d/m/Y à H:i') }}</small></div>
                <div><strong>{{ number_format((float) $refund->amount, 2, ',', ' ') }} {{ $refund->currency }}</strong><span class="status-badge status-badge--{{ $refund->status->value }}">{{ $refundLabels[$refund->status->value] ?? $refund->status->value }}</span></div>
                <p>{{ $refund->reason }}</p>
            </article>
        @empty
            <p class="payment-muted">Aucune demande de remboursement enregistrée.</p>
        @endforelse

        @if($payment->status === \App\Enums\PaymentStatus::SUCCEEDED && !$hasOpenRefund)
            <form method="POST" action="{{ route('client.payments.refunds.store', $payment) }}" class="payment-refund-form">
                @csrf
                <h3>Demander un examen de remboursement</h3>
                <p>Cette action transmet une demande à examiner. Elle ne confirme pas un remboursement et ne transfère pas d’argent.</p>
                <label for="refund-reason">Expliquez le problème</label>
                <textarea id="refund-reason" name="reason" rows="4" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea>
                <button class="btn btn-secondary" type="submit">Envoyer la demande</button>
            </form>
        @elseif($hasOpenRefund)
            <p class="payment-muted">Une demande est déjà ouverte pour ce paiement. Une nouvelle demande identique n’est pas créée.</p>
        @endif
    </section>

    <footer class="payment-detail-footer">
        <a class="btn btn-secondary" href="{{ route('client.payments.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à mes paiements</a>
        @if($payment->status !== \App\Enums\PaymentStatus::SUCCEEDED && $payment->order?->status === \App\Enums\OrderStatus::PENDING_PAYMENT && $payment->status->isFinal())
            <a class="btn btn-primary" href="{{ route('client.orders.payment', $payment->order) }}">Nouvelle tentative</a>
        @endif
        <a class="payment-text-link" href="{{ route('client.orders.show', $payment->order) }}">Voir la commande</a>
    </footer>
</div>
@endsection
