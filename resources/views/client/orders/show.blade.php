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
        'delivery_submitted' => 'Livrable à vérifier',
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
    $latestScheduleEvent = $order->statusHistories->last(fn ($history) => in_array(data_get($history->metadata, 'event'), ['schedule_proposed', 'schedule_confirmed', 'schedule_change_requested'], true));
    $latestDelivery = $order->statusHistories->last(fn ($history) => ($history->to_status?->value ?? $history->to_status) === 'delivery_submitted' && data_get($history->metadata, 'event') === 'delivery_submitted');
    $orderStatusLabel = $orderStatusLabels[$order->status->value] ?? 'Statut à vérifier';
    $nextActionTitle = 'Consulter le suivi';
    $nextActionDescription = 'Retrouvez les informations enregistrées et les dernières mises à jour de cette commande.';
    $nextActionUrl = '#order-progress-history';
    $nextActionLabel = 'Voir le suivi';
    if ($order->status === \App\Enums\OrderStatus::PENDING_PAYMENT) {
        if (! $order->payment || in_array($order->payment->status->value, ['failed', 'cancelled', 'expired'], true)) {
            $nextActionTitle = 'Le paiement reste à effectuer';
            $nextActionDescription = 'Vérifiez le récapitulatif et utilisez uniquement le parcours de démonstration disponible dans cet environnement.';
            $nextActionUrl = route('client.orders.payment', $order);
            $nextActionLabel = 'Accéder au paiement';
        } else {
            $nextActionTitle = 'Vérifiez l’état du paiement';
            $nextActionDescription = 'Un dossier de paiement existe déjà. Consultez son état enregistré avant de recommencer une tentative.';
            $nextActionUrl = route('client.payments.show', $order->payment);
            $nextActionLabel = 'Consulter le paiement';
        }
    } elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED && data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_proposed' && ! $order->schedule_confirmed_at) {
        $nextActionTitle = 'Votre décision est attendue pour le planning';
        $nextActionDescription = 'Examinez les dates proposées avant de les confirmer ou de demander une modification.';
        $nextActionUrl = '#order-schedule';
        $nextActionLabel = 'Examiner le planning';
    } elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED && data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_change_requested') {
        $nextActionTitle = 'Une nouvelle proposition de planning est attendue';
        $nextActionDescription = 'Votre demande est enregistrée. L’ancien planning reste dans l’historique jusqu’à une nouvelle proposition.';
    } elseif ($order->status === \App\Enums\OrderStatus::IN_PROGRESS) {
        $nextActionTitle = 'La prestation est en cours';
        $nextActionDescription = 'Consultez les mises à jour et échangez avec le professionnel si une précision est nécessaire.';
        $nextActionLabel = 'Suivre la réalisation';
    } elseif ($order->status === \App\Enums\OrderStatus::DELIVERY_SUBMITTED) {
        $nextActionTitle = 'Le livrable attend votre vérification';
        $nextActionDescription = 'Lisez le résumé, téléchargez les fichiers utiles, puis acceptez la livraison ou demandez une correction.';
        $nextActionUrl = '#delivery-review';
        $nextActionLabel = 'Vérifier le livrable';
    } elseif ($order->status === \App\Enums\OrderStatus::COMPLETED && ! $order->review) {
        $nextActionTitle = 'La commande est terminée';
        $nextActionDescription = 'Vous pouvez maintenant partager votre évaluation de la prestation réellement reçue.';
        $nextActionUrl = '#client-review-title';
        $nextActionLabel = 'Évaluer la prestation';
    } elseif (in_array($order->status, [\App\Enums\OrderStatus::CANCELLED, \App\Enums\OrderStatus::DISPUTED], true)) {
        $nextActionTitle = $order->status === \App\Enums\OrderStatus::DISPUTED ? 'Cette commande est contestée' : 'Cette commande est annulée';
        $nextActionDescription = 'Consultez les informations et événements conservés. Aucune étape de progression normale n’est supposée.';
    }
@endphp

<div class="client-module client-order-detail">
    <header class="client-module__header">
        <div>
            <p class="client-module__eyebrow">DÉTAIL DE LA COMMANDE</p>
            <h1>{{ $order->items->first()?->service_title ?? 'Prestation de service' }}</h1>
            <p>Référence <strong>{{ $order->order_number ?? '#'.$order->id }}</strong></p>
        </div>
        <span class="status-badge status-badge--{{ $order->status->value }}">{{ $orderStatusLabel }}</span>
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

    <section class="client-panel client-order-next-action" aria-labelledby="order-next-action-title">
        <div class="client-order-next-action__content">
            <span class="client-order-next-action__icon" aria-hidden="true"><i class="fa-solid fa-compass"></i></span>
            <p class="client-module__eyebrow">PROCHAINE ÉTAPE</p>
            <h2 id="order-next-action-title">{{ $nextActionTitle }}</h2>
            <p>{{ $nextActionDescription }}</p>
        </div>
        <a class="btn btn-primary" href="{{ $nextActionUrl }}">{{ $nextActionLabel }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
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
                        <strong>{{ $history->to_status ? ($orderStatusLabels[$history->to_status->value] ?? 'Mise à jour de la commande') : 'Événement enregistré' }}</strong>
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
                <span class="status-badge status-badge--{{ $order->payment->status->value }}">{{ $paymentStatusLabels[$order->payment->status->value] ?? 'État du paiement à vérifier' }}</span>
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


    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="client-order-error-summary alert alert--error" role="alert" tabindex="-1" data-order-error-summary>
            <h2>Votre action n’a pas pu être enregistrée</h2>
            <ul>
                @foreach ($errors->getMessages() as $field => $messages)
                    @foreach ($messages as $message)
                        <li>@if ($field === 'reason')<a href="#{{ $order->status === \App\Enums\OrderStatus::DELIVERY_SUBMITTED ? 'delivery-change-reason' : 'schedule-change-reason' }}">{{ $message }}</a>@else{{ $message }}@endif</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    @if ($order->status === \App\Enums\OrderStatus::COMPLETED)
        <section class="client-panel client-order-review" aria-labelledby="client-review-title">
            <p class="client-module__eyebrow">VOTRE EXPÉRIENCE</p>
            <h2 id="client-review-title">Évaluer cette prestation</h2>
            @if ($order->review)
                <div class="client-review-existing">
                    <p>
                        <strong>Votre note : {{ $order->review->rating }}/5</strong>
                        <span aria-label="{{ $order->review->rating }} étoiles sur 5">
                            @for ($star = 1; $star <= 5; $star++)
                                <span class="{{ $star <= $order->review->rating ? 'client-review-star client-review-star--filled' : 'client-review-star' }}" aria-hidden="true">★</span>
                            @endfor
                        </span>
                    </p>
                    @if (filled($order->review->comment))
                        <blockquote>{{ $order->review->comment }}</blockquote>
                    @else
                        <p class="client-order-muted">Vous avez laissé une note sans commentaire.</p>
                    @endif
                    @if ($order->review->status === \App\Enums\ReviewStatus::PUBLISHED)
                        <span class="status-badge status-badge--published">Avis publié</span>
                    @else
                        <p class="client-order-muted">Cet avis n’est pas actuellement visible publiquement. La modération de PROXIWORK est responsable de cette décision.</p>
                    @endif
                    @if ($order->review->response?->status === \App\Enums\ReviewStatus::PUBLISHED)
                        <div class="client-review-response">
                            <strong>Réponse du professionnel</strong>
                            <p>{{ $order->review->response->response }}</p>
                        </div>
                        <details class="client-review-report">
                            <summary>Signaler cette réponse à PROXIWORK</summary>
                            <form method="POST" action="{{ route('client.orders.review-response.report', $order) }}">
                                @csrf
                                <input type="hidden" name="target_type" value="review_response">
                                <input type="hidden" name="target_id" value="{{ $order->review->response->id }}">
                                <label for="response-report-reason">Motif du signalement</label>
                                <select id="response-report-reason" name="reason_code" required>
                                    <option value="">Sélectionner un motif</option>
                                    <option value="SPAM">Spam ou contenu répétitif</option>
                                    <option value="FRAUD">Fraude ou contenu trompeur</option>
                                    <option value="HARASSMENT">Harcèlement ou menace</option>
                                    <option value="ABUSIVE_CONTENT">Contenu injurieux</option>
                                    <option value="INAPPROPRIATE_CONTENT">Divulgation de données privées ou contenu inapproprié</option>
                                    <option value="OTHER">Autre motif</option>
                                </select>
                                <label for="response-report-description">Détails utiles (facultatif)</label>
                                <textarea id="response-report-description" name="description" rows="2" maxlength="5000" placeholder="Décrivez le problème sans ajouter de données personnelles inutiles."></textarea>
                                <button class="btn btn-secondary" type="submit">Transmettre le signalement</button>
                            </form>
                        </details>
                    @endif
                </div>
            @else
                <p>Votre commande est terminée. Partagez une évaluation honnête de la prestation que vous avez réellement reçue.</p>
                <form method="POST" action="{{ route('client.orders.review.store', $order) }}" class="client-review-form">
                    @csrf
                    <fieldset>
                        <legend>Votre note (obligatoire)</legend>
                        <div class="client-review-rating-options">
                            @for ($star = 5; $star >= 1; $star--)
                                <label for="review-rating-{{ $star }}">
                                    <input id="review-rating-{{ $star }}" type="radio" name="rating" value="{{ $star }}" @checked((string) old('rating') === (string) $star) required>
                                    <span aria-hidden="true">★</span>
                                    <span>{{ $star }}</span>
                                </label>
                            @endfor
                        </div>
                        @error('rating')<small class="field-error">{{ $message }}</small>@enderror
                    </fieldset>
                    <label for="review-comment">Votre commentaire (facultatif)</label>
                    <textarea id="review-comment" name="comment" rows="4" maxlength="2000" placeholder="Décrivez votre expérience de manière honnête et respectueuse.">{{ old('comment') }}</textarea>
                    @error('comment')<small class="field-error">{{ $message }}</small>@enderror
                    <p class="client-order-muted">Votre avis concerne cette commande. N’incluez pas de coordonnées, d’adresse ou d’informations privées.</p>
                    <button class="btn btn-primary" type="submit" data-confirm-submit="Publier votre note et votre commentaire ? Vous ne pourrez pas modifier la note après publication.">Publier mon avis</button>
                </form>
            @endif
        </section>
    @endif

    @if ($order->schedule_confirmed_at)
        <section id="order-schedule" class="client-panel client-order-schedule">
            <p class="client-module__eyebrow">PLANIFICATION</p>
            <h2>Planning confirmé</h2>
            <div class="client-order-schedule__dates"><div class="client-order-schedule__date"><span>Début convenu</span><strong>{{ $order->scheduled_start_at?->format('d/m/Y à H:i') ?? '—' }}</strong></div><div class="client-order-schedule__date"><span>Fin prévue</span><strong>{{ $order->scheduled_end_at?->format('d/m/Y à H:i') ?? '—' }}</strong></div></div>
            <p class="client-order-form-hint">Fuseau horaire de l’application : {{ config('app.timezone') }}.</p>
            <p class="muted">Confirmé le {{ $order->schedule_confirmed_at->format('d/m/Y à H:i') }}.</p>
            @if ($order->status === \App\Enums\OrderStatus::CONFIRMED)
                <form method="POST" action="{{ route('client.orders.request-schedule-change', $order) }}">
                    @csrf
                    <label for="schedule-change-reason">Pourquoi souhaitez-vous modifier le planning ?</label>
                    <p class="client-order-form-hint" id="schedule-change-hint">Indiquez les contraintes ou dates à prendre en compte. Motif obligatoire : 10 à 2 000 caractères.</p>
                    <textarea id="schedule-change-reason" name="reason" rows="3" minlength="10" maxlength="2000" required aria-describedby="schedule-change-hint schedule-change-count" data-character-counter="schedule-change-count">{{ old('reason') }}</textarea>
                    <p class="client-order-character-count"><span id="schedule-change-count" aria-live="polite">{{ mb_strlen((string) old('reason')) }}</span>/2 000 caractères</p>
                    @error('reason')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="btn btn-secondary" type="submit" data-confirm-submit="Demander une modification du planning convenu ?">Demander une modification</button>
                </form>
            @endif
        </section>
    @elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED && data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_proposed')
        <section class="client-panel client-order-schedule">
            <p class="client-module__eyebrow">PLANIFICATION À CONFIRMER</p>
            <h2>Le professionnel propose ces dates</h2>
            <div class="client-order-schedule__dates"><div class="client-order-schedule__date"><span>Début proposé</span><strong>{{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_start_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</strong></div><div class="client-order-schedule__date"><span>Fin prévue</span><strong>{{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_end_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</strong></div></div>
            <p class="client-order-form-hint">Fuseau horaire de l’application : {{ config('app.timezone') }}. Ces dates ne seront convenues qu’après confirmation enregistrée.</p>
            @if (filled(data_get($latestScheduleEvent->metadata, 'note')))
                <p>{{ data_get($latestScheduleEvent->metadata, 'note') }}</p>
            @endif
            <div class="client-order-delivery__actions">
                <form method="POST" action="{{ route('client.orders.confirm-schedule', $order) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" data-confirm-submit="Confirmer ces dates de réalisation ?">Confirmer le planning</button>
                </form>
                <form method="POST" action="{{ route('client.orders.request-schedule-change', $order) }}">
                    @csrf
                    <label for="schedule-change-reason">Pourquoi souhaitez-vous modifier le planning ?</label>
                    <p class="client-order-form-hint" id="schedule-change-hint">Décrivez les contraintes ou dates à prendre en compte. Motif obligatoire : 10 à 2 000 caractères.</p>
                    <textarea id="schedule-change-reason" name="reason" rows="3" minlength="10" maxlength="2000" required aria-describedby="schedule-change-hint schedule-change-count" data-character-counter="schedule-change-count">{{ old('reason') }}</textarea>
                    <p class="client-order-character-count"><span id="schedule-change-count" aria-live="polite">{{ mb_strlen((string) old('reason')) }}</span>/2 000 caractères</p>
                    @error('reason')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="btn btn-secondary" type="submit">Demander une modification</button>
                </form>
            </div>
        </section>
    @elseif (data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_change_requested')
        <section class="client-panel client-order-schedule">
            <h2>Modification du planning demandée</h2>
            <p>Le professionnel doit proposer de nouvelles dates. L’ancien planning reste conservé dans l’historique.</p>
        </section>
    @endif

    @if ($order->status === \App\Enums\OrderStatus::DELIVERY_SUBMITTED)
        <section id="delivery-review" class="client-panel client-order-delivery">
            <p class="client-module__eyebrow">RÉCEPTION DE LA PRESTATION</p>
            <h2>Le professionnel a soumis le résultat</h2>
            <p class="client-order-form-hint">L’acceptation termine la commande. Si un élément doit être repris, envoyez plutôt une demande de correction détaillée.</p>
            <p>Vérifiez le travail décrit ci-dessous. Confirmez la réception si le résultat correspond à ce qui a été convenu, ou demandez une correction en précisant les éléments à reprendre.</p>
            @if ($latestDelivery)
                <div class="client-order-delivery__summary">
                    <h3>Résumé du livrable</h3>
                    <p>{{ data_get($latestDelivery->metadata, 'delivery_summary') }}</p>
                    <p class="muted">Soumis le {{ $latestDelivery->created_at?->format('d/m/Y à H:i') ?? '—' }}</p>
                    @php($latestAttachmentIds = data_get($latestDelivery->metadata, 'attachment_ids', []))
                    @if ($order->deliveryAttachments->whereIn('id', $latestAttachmentIds)->isNotEmpty())
                        <h3>Fichiers remis</h3>
                        <ul class="client-order-delivery-files">
                            @foreach ($order->deliveryAttachments->whereIn('id', $latestAttachmentIds) as $attachment)
                                <li><a href="{{ route('client.orders.delivery-attachments.download', [$order, $attachment]) }}"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i><span>{{ $attachment->original_name }}</span></a><span>{{ number_format($attachment->size_bytes / 1024, 1, ',', ' ') }} Ko · Téléchargement privé</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
            <div class="client-order-delivery__actions">
                <form method="POST" action="{{ route('client.orders.accept-delivery', $order) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" data-confirm-submit="Confirmer que vous avez reçu la prestation ? Cette action clôturera la commande.">Confirmer la réception</button>
                </form>
                <form method="POST" action="{{ route('client.orders.request-delivery-changes', $order) }}">
                    @csrf
                    <label for="delivery-change-reason">Quelles corrections souhaitez-vous ?</label>
                    <p class="client-order-form-hint" id="delivery-change-hint">Précisez l’élément à reprendre, le résultat attendu et le fichier concerné si utile. Motif obligatoire : 10 à 2 000 caractères.</p>
                    <textarea id="delivery-change-reason" name="reason" rows="4" minlength="10" maxlength="2000" required aria-describedby="delivery-change-hint delivery-change-count" data-character-counter="delivery-change-count">{{ old('reason') }}</textarea>
                    <p class="client-order-character-count"><span id="delivery-change-count" aria-live="polite">{{ mb_strlen((string) old('reason')) }}</span>/2 000 caractères</p>
                    @error('reason')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="btn btn-secondary" type="submit" data-confirm-submit="Envoyer cette demande de correction au professionnel ?">Demander une correction</button>
                </form>
            </div>
        </section>
    @endif

    <section id="order-progress-history" class="client-panel client-order-delivery-history">
        <h2>Suivi de réalisation</h2>
        @forelse ($order->statusHistories->filter(fn ($history) => filled(data_get($history->metadata, 'event'))) as $event)
            <article class="timeline-item">
                <strong>
                    @switch(data_get($event->metadata, 'event'))
                        @case('schedule_proposed') Planning proposé @break
                        @case('schedule_confirmed') Planning confirmé @break
                        @case('schedule_change_requested') Modification du planning demandée @break
                        @case('work_started') Prestation démarrée @break
                        @case('delivery_submitted') Résultat soumis @break
                        @case('delivery_accepted') Réception confirmée @break
                        @case('delivery_changes_requested') Correction demandée @break
                        @case('work_blocked') Difficulté signalée @break
                        @default Mise à jour de la commande
                    @endswitch
                </strong>
                <span>{{ $event->created_at?->format('d/m/Y à H:i') ?? '—' }}</span>
                @if (filled(data_get($event->metadata, 'delivery_summary')))
                    <p>{{ data_get($event->metadata, 'delivery_summary') }}</p>
                @endif
                @if (data_get($event->metadata, 'event') === 'schedule_proposed')
                    <p>Début proposé : {{ \Carbon\CarbonImmutable::parse(data_get($event->metadata, 'proposed_start_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                    <p>Fin prévue : {{ \Carbon\CarbonImmutable::parse(data_get($event->metadata, 'proposed_end_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                    @if (filled(data_get($event->metadata, 'note')))<p>{{ data_get($event->metadata, 'note') }}</p>@endif
                @endif
                @php($eventAttachmentIds = data_get($event->metadata, 'attachment_ids', []))
                @if (is_array($eventAttachmentIds) && $eventAttachmentIds !== [])
                    <ul class="client-order-delivery-files">
                        @foreach ($order->deliveryAttachments->whereIn('id', $eventAttachmentIds) as $attachment)
                            <li><a href="{{ route('client.orders.delivery-attachments.download', [$order, $attachment]) }}"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i><span>{{ $attachment->original_name }}</span></a><span>Téléchargement privé</span></li>
                        @endforeach
                    </ul>
                @endif
                @if (filled(data_get($event->metadata, 'description')))
                    <p>{{ data_get($event->metadata, 'description') }}</p>
                @endif
                @if (filled(data_get($event->metadata, 'reason')))
                    <p><strong>Motif :</strong> {{ data_get($event->metadata, 'reason') }}</p>
                @endif
            </article>
        @empty
            <p class="muted">Aucune mise à jour de réalisation pour le moment.</p>
        @endforelse
    </section>

</div>
@endsection
