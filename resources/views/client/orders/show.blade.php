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


    @php
        $latestScheduleEvent = $order->statusHistories->last(
            fn ($history) => in_array(data_get($history->metadata, 'event'), ['schedule_proposed', 'schedule_confirmed', 'schedule_change_requested'], true)
        );
        $latestDelivery = $order->statusHistories->last(
            fn ($history) => ($history->to_status?->value ?? $history->to_status) === 'delivery_submitted'
                && data_get($history->metadata, 'event') === 'delivery_submitted'
        );
    @endphp

    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert--error" role="alert">
            <strong>Votre action n’a pas pu être enregistrée.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
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
                        <details class="client-review-report" @if ($errors->has('reason_code') || $errors->has('description')) open @endif>
                            <summary>Signaler cette réponse à PROXIWORK</summary>
                            <p class="client-review-report__intro">Utilisez ce formulaire si la réponse enfreint les règles de la plateforme. Le signalement sera examiné par la modération et ne supprime pas automatiquement le contenu.</p>
                            <form method="POST" action="{{ route('client.orders.review-response.report', $order) }}">
                                @csrf
                                <input type="hidden" name="target_type" value="review_response">
                                <input type="hidden" name="target_id" value="{{ $order->review->response->id }}">
                                <label for="response-report-reason">Motif du signalement</label>
                                <select id="response-report-reason" name="reason_code" required @if ($errors->has('reason_code')) aria-invalid="true" aria-describedby="response-report-reason-error" @endif>
                                    <option value="">Sélectionner un motif</option>
                                    <option value="SPAM" @selected(old('reason_code') === 'SPAM')>Spam ou contenu répétitif</option>
                                    <option value="FRAUD" @selected(old('reason_code') === 'FRAUD')>Fraude ou contenu trompeur</option>
                                    <option value="HARASSMENT" @selected(old('reason_code') === 'HARASSMENT')>Harcèlement ou menace</option>
                                    <option value="ABUSIVE_CONTENT" @selected(old('reason_code') === 'ABUSIVE_CONTENT')>Contenu injurieux</option>
                                    <option value="INAPPROPRIATE_CONTENT" @selected(old('reason_code') === 'INAPPROPRIATE_CONTENT')>Divulgation de données privées ou contenu inapproprié</option>
                                    <option value="OTHER" @selected(old('reason_code') === 'OTHER')>Autre motif</option>
                                </select>
                                @error('reason_code')<small id="response-report-reason-error" class="field-error" role="alert">{{ $message }}</small>@enderror
                                <label for="response-report-description">Détails utiles (facultatif)</label>
                                <textarea id="response-report-description" name="description" rows="3" maxlength="5000" aria-describedby="response-report-description-help @if ($errors->has('description')) response-report-description-error @endif" @if ($errors->has('description')) aria-invalid="true" @endif placeholder="Décrivez le problème sans ajouter de données personnelles inutiles.">{{ old('description') }}</textarea>
                                <small id="response-report-description-help" class="client-order-muted">Facultatif · 5 000 caractères maximum. Restez factuel et évitez les données personnelles inutiles.</small>
                                @error('description')<small id="response-report-description-error" class="field-error" role="alert">{{ $message }}</small>@enderror
                                <button class="btn btn-secondary" type="submit" data-confirm-submit="Transmettre ce signalement à la modération ? Cela ne supprime pas automatiquement la réponse.">Transmettre le signalement</button>
                            </form>
                        </details>
                    @endif
                </div>
            @else
                <p>Votre commande est terminée. Partagez une évaluation honnête de la prestation que vous avez réellement reçue.</p>
                <form method="POST" action="{{ route('client.orders.review.store', $order) }}" class="client-review-form">
                    @csrf
                    <fieldset @if ($errors->has('rating')) aria-describedby="review-rating-error" aria-invalid="true" @endif>
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
                        @error('rating')<small id="review-rating-error" class="field-error" role="alert">{{ $message }}</small>@enderror
                    </fieldset>
                    <label for="review-comment">Votre commentaire (facultatif)</label>
                    <textarea id="review-comment" name="comment" rows="4" maxlength="2000" aria-describedby="review-comment-help @if ($errors->has('comment')) review-comment-error @endif" @if ($errors->has('comment')) aria-invalid="true" @endif placeholder="Décrivez votre expérience de manière honnête et respectueuse.">{{ old('comment') }}</textarea>
                    <small id="review-comment-help" class="client-order-muted">2 000 caractères maximum. Évitez les coordonnées et les informations privées.</small>
                    @error('comment')<small id="review-comment-error" class="field-error" role="alert">{{ $message }}</small>@enderror
                    <p class="client-order-muted">Votre avis concerne cette commande. N’incluez pas de coordonnées, d’adresse ou d’informations privées.</p>
                    <button class="btn btn-primary" type="submit" data-confirm-submit="Publier votre note et votre commentaire ? Vous ne pourrez pas modifier la note après publication.">Publier mon avis</button>
                </form>
            @endif
        </section>
    @endif

    @if ($order->schedule_confirmed_at)
        <section class="client-panel client-order-schedule">
            <p class="client-module__eyebrow">PLANIFICATION</p>
            <h2>Planning confirmé</h2>
            <p>Début convenu : <strong>{{ $order->scheduled_start_at?->format('d/m/Y à H:i') ?? '—' }}</strong></p>
            <p>Fin prévue : <strong>{{ $order->scheduled_end_at?->format('d/m/Y à H:i') ?? '—' }}</strong></p>
            <p class="muted">Confirmé le {{ $order->schedule_confirmed_at->format('d/m/Y à H:i') }}.</p>
            @if ($order->status === \App\Enums\OrderStatus::CONFIRMED)
                <form method="POST" action="{{ route('client.orders.request-schedule-change', $order) }}">
                    @csrf
                    <label for="schedule-change-reason">Demander une modification</label>
                    <textarea id="schedule-change-reason" name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea>
                    @error('reason')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="btn btn-secondary" type="submit" data-confirm-submit="Demander une modification du planning convenu ?">Demander une modification</button>
                </form>
            @endif
        </section>
    @elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED && data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_proposed')
        <section class="client-panel client-order-schedule">
            <p class="client-module__eyebrow">PLANIFICATION À CONFIRMER</p>
            <h2>Le professionnel propose ces dates</h2>
            <p>Début proposé : <strong>{{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_start_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</strong></p>
            <p>Fin prévue : <strong>{{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_end_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</strong></p>
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
                    <label for="schedule-change-reason">Dates ou conditions à modifier</label>
                    <textarea id="schedule-change-reason" name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea>
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
        <section class="client-panel client-order-delivery">
            <p class="client-module__eyebrow">RÉCEPTION DE LA PRESTATION</p>
            <h2>Le professionnel a soumis le résultat</h2>
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
                                <li><a href="{{ route('client.orders.delivery-attachments.download', [$order, $attachment]) }}">{{ $attachment->original_name }}</a> <span class="muted">({{ number_format($attachment->size_bytes / 1024, 1, ',', ' ') }} Ko)</span></li>
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
                    <label for="delivery-change-reason">Corrections demandées</label>
                    <textarea id="delivery-change-reason" name="reason" rows="4" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea>
                    @error('reason')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="btn btn-secondary" type="submit" data-confirm-submit="Envoyer cette demande de correction au professionnel ?">Demander une correction</button>
                </form>
            </div>
        </section>
    @endif

    <section class="client-panel client-order-delivery-history">
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
                            <li><a href="{{ route('client.orders.delivery-attachments.download', [$order, $attachment]) }}">{{ $attachment->original_name }}</a></li>
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
