@extends('layouts.admin')

@push('head')
    @unless(app()->environment('testing'))
        @vite('resources/css/pages/admin/orders.css')
    @endunless
@endpush

@section('page_title', $order->order_number)
@section('page_description', 'Dossier de supervision commercial, opérationnel et financier. Aucune modification contractuelle ou financière directe depuis cet écran.')

@section('content')
@php
    $statusLabels = [
        'pending_payment' => 'En attente de paiement',
        'confirmed' => 'Confirmée',
        'in_progress' => 'En cours',
        'delivery_submitted' => 'Livrable soumis',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        'disputed' => 'Contestée',
    ];
@endphp

<div class="admin-order-detail-head">
    <div>
        <span class="admin-status admin-status--{{ $order->status->value }}">{{ $statusLabels[$order->status->value] ?? $order->status->value }}</span>
        <p>Créée le {{ $order->created_at?->format('d/m/Y à H:i') ?? '—' }} · Dernière mise à jour {{ $order->updated_at?->format('d/m/Y à H:i') ?? '—' }}</p>
    </div>
    <a class="admin-link" href="{{ route('admin.orders.index') }}">← Retour aux commandes</a>
</div>

<div class="admin-transaction-grid">
    <section class="admin-card">
        <h3>1. Engagement commercial</h3>
        <div class="admin-detail-list">
            <div><span>Référence</span><strong>{{ $order->order_number }}</strong></div>
            <div><span>Statut</span><strong>{{ $statusLabels[$order->status->value] ?? $order->status->value }}</strong></div>
            <div><span>Sous-total</span><strong>{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}</strong></div>
            <div><span>Total contractuel</span><strong>{{ number_format((float) $order->total, 2) }} {{ $order->currency }}</strong></div>
            <div><span>Demande d'origine</span><strong>{{ $order->serviceRequest?->title ?? '—' }}</strong></div>
            <div><span>Service</span><strong>{{ $order->serviceRequest?->service?->title ?? '—' }}</strong></div>
            <div><span>Catégorie</span><strong>{{ $order->serviceRequest?->service?->category?->name ?? '—' }}</strong></div>
            <div><span>Devis</span><strong>{{ $order->quotation?->id ? '#'.$order->quotation->id : '—' }}</strong></div>
            <div><span>Offre acceptée</span><strong>{{ $order->accepted_offer_id ? '#'.$order->accepted_offer_id : '—' }}</strong></div>
            @if($order->acceptedOffer)
                <div><span>Montant de l'offre acceptée</span><strong>{{ number_format((float) $order->acceptedOffer->amount, 2) }} {{ $order->acceptedOffer->currency }}</strong></div>
                <div><span>Durée convenue</span><strong>{{ $order->acceptedOffer->duration_value ?? '—' }} {{ $order->acceptedOffer->duration_unit?->value ?? '' }}</strong></div>
                <div><span>Validité de l'offre</span><strong>{{ $order->acceptedOffer->valid_until?->format('d/m/Y H:i') ?? '—' }}</strong></div>
                <div><span>Contenu de l'offre acceptée</span><strong>{{ $order->acceptedOffer->description ?: 'Non renseigné' }}</strong></div>
                <div><span>Conditions convenues</span><strong>{{ $order->acceptedOffer->conditions ?: 'Aucune condition complémentaire' }}</strong></div>
            @endif
        </div>
    </section>

    <section class="admin-card">
        <h3>2. Parties concernées</h3>
        <div class="admin-detail-list">
            <div><span>Client</span><strong>{{ $order->client?->name ?? '—' }}</strong></div>
            <div><span>E-mail client</span><strong>{{ $order->client?->email ?? '—' }}</strong></div>
            <div><span>Professionnel</span><strong>{{ $order->professional?->user?->name ?? '—' }}</strong></div>
            <div><span>E-mail professionnel</span><strong>{{ $order->professional?->user?->email ?? '—' }}</strong></div>
            <div><span>Adresse d'exécution</span><strong>{{ $order->addressSnapshot?->address_line ?? 'Adresse conservée dans le snapshot' }}</strong></div>
        </div>
        <p class="admin-order-note">Les coordonnées et snapshots sont affichés pour le traitement autorisé de cette commande. Ils ne doivent pas être réutilisés hors du dossier.</p>
    </section>

    <section class="admin-card">
        <h3>3. Exécution et planning</h3>
        <div class="admin-detail-list">
            <div><span>Acceptation</span><strong>{{ $order->accepted_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Confirmation</span><strong>{{ $order->confirmed_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Début de prestation</span><strong>{{ $order->started_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Début planifié</span><strong>{{ $order->scheduled_start_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Fin planifiée</span><strong>{{ $order->scheduled_end_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Planning confirmé</span><strong>{{ $order->schedule_confirmed_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Fin effective</span><strong>{{ $order->completed_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
            <div><span>Annulation</span><strong>{{ $order->cancelled_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
        </div>
    </section>

    <section class="admin-card">
        <h3>4. Situation financière (lecture seule)</h3>
        @if($order->payment)
            <div class="admin-detail-list">
                <div><span>État du paiement</span><strong>{{ $order->payment->status->value }}</strong></div>
                <div><span>Montant du paiement</span><strong>{{ number_format((float) $order->payment->amount, 2) }} {{ $order->payment->currency }}</strong></div>
                <div><span>Payé le</span><strong>{{ $order->payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
                <div><span>Fournisseur</span><strong>{{ $order->payment->provider?->value ?? $order->payment->provider ?? '—' }}</strong></div>
            </div>
            <h4>Transactions enregistrées</h4>
            @forelse($order->payment->transactions as $transaction)
                <p>{{ $transaction->created_at?->format('d/m/Y H:i') ?? '—' }} · {{ $transaction->status?->value ?? $transaction->status ?? 'Transaction' }}</p>
            @empty
                <p>Aucune transaction associée.</p>
            @endforelse
            <h4>Remboursements</h4>
            @forelse($order->payment->refunds as $refund)
                <p>{{ $refund->created_at?->format('d/m/Y H:i') ?? '—' }} · {{ $refund->status?->value ?? $refund->status ?? 'Remboursement' }} · {{ isset($refund->amount) ? number_format((float) $refund->amount, 2).' '.($order->payment->currency ?? '') : 'Montant non disponible' }}</p>
            @empty
                <p>Aucun remboursement enregistré.</p>
            @endforelse
        @else
            <p>Aucun paiement associé à ce dossier.</p>
        @endif
        <div class="admin-detail-list">
            <div><span>Commission</span><strong>{{ $order->commission ? number_format((float) $order->commission->commission_amount, 2).' '.$order->currency : 'Non enregistrée' }}</strong></div>
            @if($order->commission)
                <div><span>Net professionnel</span><strong>{{ number_format((float) $order->commission->net_amount, 2) }} {{ $order->currency }}</strong></div>
                <div><span>État commission</span><strong>{{ $order->commission->status->value }}</strong></div>
            @endif
            <div><span>Revue client</span><strong>{{ $order->review ? 'Enregistrée' : 'Aucune' }}</strong></div>
        </div>
        <p class="admin-order-note">Le total contractuel, le paiement encaissé, les remboursements et la commission sont des données distinctes. Cet écran ne déclenche aucune opération financière.</p>
    </section>

    <section class="admin-card">
        <h3>5. Articles de la commande</h3>
        <div class="admin-detail-list">
            @forelse($order->items as $item)
                <div><span>{{ $item->service_title }} × {{ $item->quantity }}</span><strong>{{ number_format((float) $item->subtotal, 2) }} {{ $item->currency }}</strong></div>
            @empty
                <p>Aucun article détaillé.</p>
            @endforelse
        </div>
    </section>

    <section class="admin-card">
        <h3>6. Livrables soumis</h3>
        @forelse($order->deliveryAttachments as $attachment)
            <div class="admin-order-attachment">
                <a href="{{ route('admin.orders.delivery-attachments.download', [$order, $attachment]) }}"><strong>{{ $attachment->original_name }}</strong></a>
                <span>{{ $attachment->mime_type }} · {{ number_format($attachment->size_bytes / 1024, 1) }} Ko</span>
                <small>Déposé par {{ $attachment->submitter?->name ?? 'Utilisateur' }} · {{ $attachment->created_at?->format('d/m/Y H:i') ?? '—' }}</small>
            </div>
        @empty
            <p>Aucun fichier de livraison enregistré.</p>
        @endforelse
        <p class="admin-order-note">Les fichiers sont conservés sur un stockage privé. Aucun chemin de stockage n'est exposé dans cette page. Le téléchargement administratif nécessite une route dédiée et une permission explicite.</p>
    </section>
</div>

<section class="admin-card admin-order-timeline">
    <h3>7. Chronologie et incidents</h3>
    @forelse($order->statusHistories as $history)
        @php($event = data_get($history->metadata, 'event'))
        <article class="admin-order-event">
            <div class="admin-order-event__meta">
                <strong>{{ $history->created_at?->format('d/m/Y H:i') ?? 'Date inconnue' }}</strong>
                <span>{{ $history->changedBy?->name ?? 'Acteur non disponible' }}</span>
            </div>
            <div>
                <strong>{{ $event ? str_replace('_', ' ', ucfirst($event)) : (($history->from_status?->value ?? '—').' → '.$history->to_status->value) }}</strong>
                <p>{{ $history->reason ?: 'Aucun motif complémentaire.' }}</p>
                @if($event === 'work_blocked')
                    <div class="admin-order-alert admin-order-alert--inline">
                        <strong>Blocage signalé</strong>
                        <p>{{ data_get($history->metadata, 'description', 'Description non fournie dans les métadonnées.') }}</p>
                    </div>
                @elseif($event === 'delivery_changes_requested')
                    <p><strong>Correction demandée :</strong> {{ data_get($history->metadata, 'reason', 'Motif non précisé.') }}</p>
                @elseif($event === 'delivery_submitted')
                    <p>{{ data_get($history->metadata, 'delivery_summary', 'Résumé du livrable non disponible.') }}</p>
                @elseif($event === 'schedule_proposed' || $event === 'schedule_confirmed')
                    <p>Début : {{ data_get($history->metadata, 'proposed_start_at', data_get($history->metadata, 'scheduled_start_at', '—')) }} · Fin : {{ data_get($history->metadata, 'proposed_end_at', data_get($history->metadata, 'scheduled_end_at', '—')) }}</p>
                @endif
            </div>
        </article>
    @empty
        <p>Aucun événement historique enregistré.</p>
    @endforelse

    @if($order->quotation)
        <h4>Historique des négociations du devis</h4>
        @forelse($order->quotation->events as $event)
            <article class="admin-order-event">
                <div class="admin-order-event__meta">
                    <strong>{{ $event->created_at?->format('d/m/Y H:i') ?? 'Date inconnue' }}</strong>
                    <span>{{ $event->actor?->name ?? 'Acteur non disponible' }}</span>
                </div>
                <div>
                    <strong>{{ str_replace('_', ' ', ucfirst($event->type)) }}</strong>
                    @if($event->offer_id)
                        <p>Offre associée : #{{ $event->offer_id }} · version {{ data_get($event->metadata, 'version', '—') }}</p>
                    @endif
                </div>
            </article>
        @empty
            <p>Aucun événement de négociation enregistré.</p>
        @endforelse
    @endif

    @if($order->payment)
        <h4>Événements financiers</h4>
        @forelse($order->payment->events as $event)
            <article class="admin-order-event">
                <div class="admin-order-event__meta">
                    <strong>{{ $event->occurred_at?->format('d/m/Y H:i') ?? 'Date inconnue' }}</strong>
                    <span>Passerelle / système de paiement</span>
                </div>
                <div>
                    <strong>{{ str_replace('_', ' ', ucfirst($event->type)) }}</strong>
                    @if(filled(data_get($event->metadata, 'message')))
                        <p>{{ data_get($event->metadata, 'message') }}</p>
                    @endif
                </div>
            </article>
        @empty
            <p>Aucun événement financier complémentaire enregistré.</p>
        @endforelse
    @endif
</section>
@endsection
