@extends('layouts.client')

@section('title', 'Détail de la commande')

@section('content')
@push('head')
    @vite('resources/css/pages/client/orders-payments.css')
@endpush

@php
    $orderStatusLabels = [
        'pending_payment' => 'En attente de paiement',
        'confirmed' => 'Confirmée / planifiée',
        'in_progress' => 'En cours',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        'disputed' => 'Contestée',
    ];
    $paymentStatusLabels = [
        'initiated' => 'Initialisé',
        'pending' => 'En attente',
        'processing' => 'En traitement',
        'succeeded' => 'Réussi',
        'failed' => 'Échoué',
        'cancelled' => 'Annulé',
        'expired' => 'Expiré',
        'refunded' => 'Remboursé',
    ];
@endphp

<div class="client-module client-order-detail">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">DÉTAIL DE LA COMMANDE</p>
            <h1>{{ $order->items->first()?->service_title ?? 'Prestation de service' }}</h1>
            <p>Référence <strong>{{ $order->order_number ?? '#'.$order->id }}</strong></p>
        </div>
        <span class="status-badge status-badge--{{ $order->status->value }}">{{ $orderStatusLabels[$order->status->value] ?? $order->status->value }}</span>
    </header>

    <section class="client-panel client-order-overview">
        <div class="client-order-overview__main">
            <span class="client-module__eyebrow">MONTANT CONVENU</span>
            <p class="client-order-overview__amount">{{ number_format((float) $order->total, 2, ',', ' ') }} <span>{{ $order->currency }}</span></p>
            <p>Conditions enregistrées à partir de l’offre acceptée. Ce montant n’est pas remplacé par le prix actuel du catalogue.</p>
        </div>
        <dl class="client-order-overview__dates">
            <div><dt>Commande créée</dt><dd>{{ optional($order->created_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            <div><dt>Devis accepté</dt><dd>{{ optional($order->accepted_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            @if($order->serviceRequest?->desired_at)
                <div><dt>Date souhaitée dans la demande</dt><dd>{{ $order->serviceRequest->desired_at->format('d/m/Y à H:i') }}</dd></div>
            @endif
            <div><dt>Commande confirmée</dt><dd>{{ optional($order->confirmed_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            <div><dt>Début enregistré</dt><dd>{{ optional($order->started_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            <div><dt>Fin enregistrée</dt><dd>{{ optional($order->completed_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            @if($order->cancelled_at)
                <div><dt>Annulation enregistrée</dt><dd>{{ $order->cancelled_at->format('d/m/Y à H:i') }}</dd></div>
            @endif
        </dl>
    </section>

    <div class="client-detail-grid client-order-detail-grid">
        <section class="client-panel">
            <h2>Professionnel</h2>
            <div class="client-order-professional">
                <span class="client-order-professional__avatar" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></span>
                <div>
                    <strong>{{ $order->professional?->user?->name ?? 'Professionnel non disponible' }}</strong>
                    @if($order->professional?->professional_title)
                        <p>{{ $order->professional->professional_title }}</p>
                    @endif
                </div>
            </div>
            @if($order->quotation)
                <a class="btn btn-secondary" href="{{ route('client.quotes.show', $order->quotation) }}">
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Consulter le devis d’origine
                </a>
            @endif
        </section>

        <section class="client-panel">
            <h2>Adresse d’exécution</h2>
            @if($order->addressSnapshot)
                <address class="client-order-address">
                    <strong>{{ $order->addressSnapshot->recipient_name }}</strong>
                    @if($order->addressSnapshot->contact_phone)
                        <span>{{ $order->addressSnapshot->contact_phone }}</span>
                    @endif
                    <span>{{ $order->addressSnapshot->address_line_1 }}{{ $order->addressSnapshot->address_line_2 ? ', '.$order->addressSnapshot->address_line_2 : '' }}</span>
                    <span>{{ collect([$order->addressSnapshot->neighborhood, $order->addressSnapshot->commune])->filter()->implode(', ') }}</span>
                    <span>{{ collect([$order->addressSnapshot->city, $order->addressSnapshot->province])->filter()->implode(', ') }}</span>
                    @if($order->addressSnapshot->landmark)
                        <span>Repère : {{ $order->addressSnapshot->landmark }}</span>
                    @endif
                    @if($order->addressSnapshot->access_instructions)
                        <span><strong>Instructions d’accès :</strong> {{ $order->addressSnapshot->access_instructions }}</span>
                    @endif
                </address>
                <p class="client-order-muted">Il s’agit de la copie de l’adresse enregistrée pour cette commande.</p>
            @else
                @if($order->serviceRequest?->execution_mode?->value === 'remote')
                    <p class="client-order-muted">Prestation à distance : aucune adresse physique n’est nécessaire ni enregistrée.</p>
                @else
                    <p class="client-order-muted">Aucune adresse d’exécution enregistrée pour cette commande.</p>
                @endif
            @endif
        </section>
    </div>

    <section class="client-panel">
        <div class="client-panel__header">
            <h2>Conditions commerciales acceptées</h2>
            <span class="client-order-muted">Copie conservée pour cette commande</span>
        </div>

        @forelse($order->items as $item)
            <article class="client-order-line">
                <div class="client-order-line__heading">
                    <div>
                        <h3>{{ $item->service_title }}</h3>
                        <p>{{ $item->service_description ?: 'Aucune description enregistrée.' }}</p>
                    </div>
                    <strong>{{ number_format((float) $item->subtotal, 2, ',', ' ') }} {{ $item->currency }}</strong>
                </div>
                <dl class="client-definition-list">
                    <div><dt>Quantité</dt><dd>{{ $item->quantity }}</dd></div>
                    <div><dt>Prix unitaire</dt><dd>{{ number_format((float) $item->unit_price, 2, ',', ' ') }} {{ $item->currency }}</dd></div>
                    @if($item->duration_value && $item->duration_unit)
                        <div><dt>Durée convenue</dt><dd>{{ $item->duration_value }} {{ $item->duration_unit }}</dd></div>
                    @endif
                </dl>
                @if($item->conditions)
                    <div class="client-order-conditions">
                        <h4>Conditions particulières</h4>
                        <p>{{ $item->conditions }}</p>
                    </div>
                @endif
            </article>
        @empty
            <p class="client-order-muted">Aucune ligne de commande n’est disponible.</p>
        @endforelse

        <dl class="client-order-total">
            <div><dt>Sous-total</dt><dd>{{ number_format((float) $order->subtotal, 2, ',', ' ') }} {{ $order->currency }}</dd></div>
            <div><dt>Total convenu</dt><dd>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency }}</dd></div>
        </dl>

        @if($order->acceptedOffer)
            <p class="client-order-muted">Offre acceptée : version {{ $order->acceptedOffer->version }} · {{ optional($order->acceptedOffer->created_at)->format('d/m/Y à H:i') ?? 'date non disponible' }}.</p>
        @endif
    </section>

    <section class="client-panel">
        <div class="client-panel__header">
            <h2>Historique de la commande</h2>
            <span class="client-order-muted">{{ $order->statusHistories->count() }} événement(s) enregistré(s)</span>
        </div>
        @if($order->statusHistories->isNotEmpty())
            <ol class="client-timeline client-order-timeline">
                @foreach($order->statusHistories as $history)
                    <li>
                        <strong>{{ $history->to_status ? ($orderStatusLabels[$history->to_status->value] ?? $history->to_status->value) : 'Événement enregistré' }}</strong>
                        <span>{{ optional($history->created_at)->format('d/m/Y à H:i') ?? 'Date non disponible' }}</span>
                        @if($history->reason)
                            <p>{{ $history->reason }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @else
            <p class="client-order-muted">Aucun événement d’historique n’est enregistré pour le moment. Aucun événement n’est déduit artificiellement du statut actuel.</p>
        @endif
    </section>

    <section class="client-panel">
        <div class="client-panel__header">
            <h2>Paiement associé</h2>
            @if($order->payment)
                <span class="status-badge status-badge--{{ $order->payment->status->value }}">{{ $paymentStatusLabels[$order->payment->status->value] ?? $order->payment->status->value }}</span>
            @endif
        </div>

        @if($order->payment)
            <dl class="client-definition-list client-order-payment-details">
                <div><dt>Montant enregistré</dt><dd><strong>{{ number_format((float) $order->payment->amount, 2, ',', ' ') }} {{ $order->payment->currency }}</strong></dd></div>
                <div><dt>Méthode</dt><dd>{{ $order->payment->method ?? '—' }}</dd></div>
                <div><dt>Fournisseur</dt><dd>{{ $order->payment->provider ?? '—' }}</dd></div>
                <div><dt>Confirmation du paiement</dt><dd>{{ optional($order->payment->paid_at)->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            </dl>

            @if($order->payment->transactions->isNotEmpty())
                <h3 class="client-order-subheading">Transactions enregistrées</h3>
                <div class="client-order-transactions">
                    @foreach($order->payment->transactions as $transaction)
                        <article>
                            <div>
                                <strong>Transaction #{{ $transaction->id }}</strong>
                                <span>Résultat : {{ $paymentStatusLabels[$transaction->status->value] ?? $transaction->status->value }}</span>
                                <span>{{ $transaction->provider_reference ? 'Référence fournisseur : '.$transaction->provider_reference : 'Référence fournisseur non disponible' }}</span>
                                <small>
                                    @if($transaction->processed_at)
                                        Traitée le {{ $transaction->processed_at->format('d/m/Y à H:i') }}
                                    @elseif($transaction->failed_at)
                                        Échec le {{ $transaction->failed_at->format('d/m/Y à H:i') }}
                                    @elseif($transaction->initiated_at)
                                        Initiée le {{ $transaction->initiated_at->format('d/m/Y à H:i') }}
                                    @else
                                        Date de transaction non disponible
                                    @endif
                                </small>
                            </div>
                            <strong>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $order->payment->currency }}</strong>
                        </article>
                    @endforeach
                </div>
            @endif

            <a class="btn btn-secondary" href="{{ route('client.payments.show', $order->payment) }}">Ouvrir le dossier de paiement</a>
        @else
            <p class="client-order-muted">Aucun paiement n’est enregistré pour cette commande. La création de la commande ne signifie pas que le paiement a réussi.</p>
            @if($order->status->value === 'pending_payment')
                <a class="btn btn-primary" href="{{ route('client.orders.payment', $order) }}">Accéder au paiement</a>
            @endif
        @endif
    </section>

    <section class="client-panel">
        <div class="client-panel__header">
            <h2>Messages liés à cette prestation</h2>
        </div>
        @if($conversations->isNotEmpty())
            <p class="client-order-muted">Les conversations ci-dessous sont liées à cette commande ou à sa demande d’origine et sont accessibles à votre compte.</p>
            <div class="client-order-transactions">
                @foreach($conversations as $conversation)
                    <article>
                        <div>
                            <strong>Conversation #{{ $conversation->id }}</strong>
                            <span>{{ ucfirst(str_replace('_', ' ', $conversation->type->value)) }} · {{ ucfirst(str_replace('_', ' ', $conversation->status->value)) }}</span>
                        </div>
                        <a class="btn btn-secondary btn-sm" href="{{ route('client.messages.show', $conversation) }}">Ouvrir les messages <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </article>
                @endforeach
            </div>
        @else
            <p class="client-order-muted">Aucune conversation liée à cette commande n’est disponible pour votre compte.</p>
            @if ($order->professional)
                <form method="POST" action="{{ route('client.messages.start', $order->professional) }}">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                    <button class="btn btn-secondary" type="submit"><i class="fa-regular fa-message" aria-hidden="true"></i> Démarrer une conversation pour cette commande</button>
                </form>
            @endif
        @endif
    </section>

    <footer class="client-action-bar client-order-footer">
        <a class="btn btn-secondary" href="{{ route('client.orders.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour aux commandes</a>
    </footer>
</div>
@endsection
