@extends('layouts.professional')

@section('title', 'Commande #'.$order->id)

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/orders-revenues.css', 'resources/js/pages/professional/orders-revenues.js'])
    @endpush
    <div class="page-header">
        <div>
            <p class="page-kicker">COMMANDE #{{ $order->id }}</p>
            <h1>Détail de la commande</h1>
            <p class="page-description">Informations historiques et financières de la commande.</p>
        </div>
        <span class="status-badge">{{ str_replace('_', ' ', $order->status->value) }}</span>
    </div>

    <div class="detail-grid">
        <section class="data-card">
            <h2>Commande</h2>
            <dl class="detail-list">
                <div><dt>Client</dt><dd>{{ $order->client?->name ?? '—' }}</dd></div>
                <div><dt>Créée le</dt><dd>{{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt>Total</dt><dd>{{ number_format((float) $order->total, 2) }} {{ $order->currency ?? '' }}</dd></div>
                <div><dt>Service</dt><dd>{{ $order->serviceRequest?->service?->title ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="data-card">
            <h2>Finances</h2>
            <dl class="detail-list">
                <div><dt>Paiement</dt><dd>{{ $order->payment?->status?->value ?? 'Non disponible' }}</dd></div>
                <div><dt>Revenu brut</dt><dd>{{ $order->commission ? number_format((float) $order->commission->gross_amount, 2).' '.$order->commission->currency : '—' }}</dd></div>
                <div><dt>Commission</dt><dd>{{ $order->commission ? number_format((float) $order->commission->commission_amount, 2).' '.$order->commission->currency : '—' }}</dd></div>
                <div><dt>Net</dt><dd>{{ $order->commission ? number_format((float) $order->commission->net_amount, 2).' '.$order->commission->currency : '—' }}</dd></div>
            </dl>
        </section>

        <section class="data-card">
            <h2>Historique</h2>
            @forelse ($order->statusHistories as $history)
                <div class="timeline-item">
                    <strong>{{ str_replace('_', ' ', $history->status->value ?? $history->status) }}</strong>
                    <span>{{ $history->created_at?->format('d/m/Y H:i') }}</span>
                </div>
            @empty
                <p class="muted">Aucun historique disponible.</p>
            @endforelse
        </section>

        <section class="data-card">
            <h2>Adresse de la commande</h2>
            @if ($order->addressSnapshot)
                <address class="order-address-snapshot">
                    @if($order->addressSnapshot->recipient_name)<strong>{{ $order->addressSnapshot->recipient_name }}</strong>@endif
                    @if($order->addressSnapshot->contact_phone)<span>{{ $order->addressSnapshot->contact_phone }}</span>@endif
                    <span>{{ $order->addressSnapshot->address_line_1 }}{{ $order->addressSnapshot->address_line_2 ? ', '.$order->addressSnapshot->address_line_2 : '' }}</span>
                    <span>{{ collect([$order->addressSnapshot->neighborhood, $order->addressSnapshot->commune, $order->addressSnapshot->city, $order->addressSnapshot->province, $order->addressSnapshot->country_code])->filter()->join(', ') }}</span>
                    @if($order->addressSnapshot->landmark)<span><strong>Repère :</strong> {{ $order->addressSnapshot->landmark }}</span>@endif
                    @if($order->addressSnapshot->access_instructions)<span><strong>Instructions d’accès :</strong> {{ $order->addressSnapshot->access_instructions }}</span>@endif
                </address>
            @else
                @if($order->serviceRequest?->execution_mode?->value === 'remote')
                    <p class="muted">Prestation à distance : aucune adresse physique du client n’est requise.</p>
                @else
                    <p class="muted">Aucun snapshot d'adresse disponible.</p>
                @endif
            @endif
        </section>
    </div>

    @if (session('success'))
        <div class="alert alert--success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert--error" role="alert">
            <strong>L’action n’a pas pu être enregistrée.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $latestDelivery = $order->statusHistories->last(
            fn ($history) => ($history->to_status?->value ?? $history->to_status) === 'delivery_submitted'
                && data_get($history->metadata, 'event') === 'delivery_submitted'
        );
    @endphp

    <section class="data-card professional-order-workflow">
        <h2>Suivi de réalisation</h2>
        <p class="muted">Le paiement, l’exécution et la réception sont suivis séparément. Une commande n’est terminée qu’après confirmation de réception par le client.</p>

        @php($latestScheduleEvent = $order->statusHistories->last(fn ($history) => in_array(data_get($history->metadata, 'event'), ['schedule_proposed', 'schedule_confirmed', 'schedule_change_requested'], true)))

        @if ($order->schedule_confirmed_at)
            <div class="professional-order-workflow__notice" role="status">
                <strong>Planning confirmé par les deux parties</strong>
                <p>Début : {{ $order->scheduled_start_at?->format('d/m/Y à H:i') ?? '—' }}</p>
                <p>Fin prévue : {{ $order->scheduled_end_at?->format('d/m/Y à H:i') ?? '—' }}</p>
                <p class="muted">Confirmation enregistrée le {{ $order->schedule_confirmed_at->format('d/m/Y à H:i') }}</p>
            </div>
        @elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED && data_get($latestScheduleEvent?->metadata, 'event') !== 'schedule_proposed')
            <form method="POST" action="{{ route('professional.orders.schedule', $order) }}" class="professional-order-schedule-form">
                @csrf
                <h3>Proposer un planning au client</h3>
                <p>Les dates restent proposées tant que le client ne les a pas confirmées. La date souhaitée par le client n’est pas automatiquement un engagement.</p>
                <label for="schedule-starts-at">Début proposé</label>
                <input id="schedule-starts-at" name="starts_at" type="datetime-local" min="{{ now()->format('Y-m-d\\TH:i') }}" value="{{ old('starts_at') }}" required>
                @error('starts_at')<small class="field-error">{{ $message }}</small>@enderror
                <label for="schedule-ends-at">Fin prévue</label>
                <input id="schedule-ends-at" name="ends_at" type="datetime-local" min="{{ old('starts_at', now()->format('Y-m-d\\TH:i')) }}" value="{{ old('ends_at') }}" required>
                @error('ends_at')<small class="field-error">{{ $message }}</small>@enderror
                <label for="schedule-note">Note (facultative)</label>
                <textarea id="schedule-note" name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
                @error('note')<small class="field-error">{{ $message }}</small>@enderror
                <button class="button button--secondary" type="submit">Envoyer la proposition</button>
            </form>
        @endif

        @if ($order->status === \App\Enums\OrderStatus::PENDING_PAYMENT)
            <div class="professional-order-workflow__notice" role="status">
                <strong>En attente de paiement</strong>
                <p>Le démarrage est désactivé jusqu’à la confirmation du paiement par PROXIWORK.</p>
            </div>
        @elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED)
            <form method="POST" action="{{ route('professional.orders.start', $order) }}">
                @csrf
                <button class="button button--primary" type="submit" data-confirm-submit="Confirmer le démarrage de cette prestation ?">Démarrer la prestation</button>
            </form>
        @elseif ($order->status === \App\Enums\OrderStatus::IN_PROGRESS)
            <div class="professional-order-workflow__forms">
                <form method="POST" enctype="multipart/form-data" action="{{ route('professional.orders.submit-delivery', $order) }}">
                    @csrf
                    <h3>Soumettre le résultat au client</h3>
                    <p>Décrivez précisément le travail effectué, les éléments remis et les éventuelles consignes de vérification.</p>
                    <label for="delivery-summary">Résumé du livrable</label>
                    <textarea id="delivery-summary" name="summary" rows="5" minlength="20" maxlength="5000" required>{{ old('summary') }}</textarea>
                    @error('summary')<small class="field-error">{{ $message }}</small>@enderror
                    <label for="delivery-attachments">Fichiers livrés (facultatif, 5 fichiers maximum)</label>
                    <input id="delivery-attachments" type="file" name="attachments[]" multiple
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip">
                    <small class="muted">PDF, images, documents Office ou ZIP. 10 Mo maximum par fichier.</small>
                    @error('attachments')<small class="field-error">{{ $message }}</small>@enderror
                    @error('attachments.*')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="button button--primary" type="submit" data-confirm-submit="Soumettre le résultat au client pour vérification ?">Soumettre pour réception</button>
                </form>

                <form method="POST" action="{{ route('professional.orders.report-blocker', $order) }}">
                    @csrf
                    <h3>Signaler une difficulté</h3>
                    <label for="blocker-description">Décrivez le blocage et la prochaine étape proposée</label>
                    <textarea id="blocker-description" name="description" rows="3" minlength="10" maxlength="2000" required>{{ old('description') }}</textarea>
                    @error('description')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="button button--secondary" type="submit">Informer le client</button>
                </form>
            </div>
        @elseif ($order->status === \App\Enums\OrderStatus::DELIVERY_SUBMITTED)
            <div class="professional-order-workflow__notice" role="status">
                <strong>Résultat soumis — réception du client attendue</strong>
                <p>Le client doit confirmer la réception ou demander une correction. Vous ne pouvez pas clôturer vous-même cette commande.</p>
            </div>
            @if ($latestDelivery)
                <h3>Dernier résultat soumis</h3>
                <p class="professional-order-workflow__summary">{{ data_get($latestDelivery->metadata, 'delivery_summary') }}</p>
                <p class="muted">Soumis le {{ $latestDelivery->created_at?->format('d/m/Y à H:i') ?? '—' }}</p>
                @php($latestAttachmentIds = data_get($latestDelivery->metadata, 'attachment_ids', []))
                @if ($order->deliveryAttachments->whereIn('id', $latestAttachmentIds)->isNotEmpty())
                    <h3>Fichiers remis au client</h3>
                    <ul class="professional-order-delivery-files">
                        @foreach ($order->deliveryAttachments->whereIn('id', $latestAttachmentIds) as $attachment)
                            <li><a href="{{ route('professional.orders.delivery-attachments.download', [$order, $attachment]) }}">{{ $attachment->original_name }}</a> <span class="muted">({{ number_format($attachment->size_bytes / 1024, 1, ',', ' ') }} Ko)</span></li>
                        @endforeach
                    </ul>
                @endif
            @endif
        @elseif ($order->status === \App\Enums\OrderStatus::COMPLETED)
            <div class="professional-order-workflow__notice" role="status">
                <strong>Réception confirmée</strong>
                <p>Le client a confirmé la réception de la prestation.</p>
            </div>
        @endif
    </section>

    <section class="data-card professional-order-workflow">
        <h2>Événements opérationnels</h2>
        @forelse ($order->statusHistories->filter(fn ($history) => filled(data_get($history->metadata, 'event'))) as $event)
            <article class="timeline-item">
                <strong>
                    @switch(data_get($event->metadata, 'event'))
                        @case('schedule_proposed') Planning proposé au client @break
                        @case('schedule_confirmed') Planning confirmé par le client @break
                        @case('schedule_change_requested') Modification du planning demandée @break
                        @case('work_started') Démarrage de la prestation @break
                        @case('delivery_submitted') Résultat soumis au client @break
                        @case('delivery_accepted') Réception confirmée par le client @break
                        @case('delivery_changes_requested') Correction demandée par le client @break
                        @case('work_blocked') Blocage signalé @break
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
                    <ul class="professional-order-delivery-files">
                        @foreach ($order->deliveryAttachments->whereIn('id', $eventAttachmentIds) as $attachment)
                            <li><a href="{{ route('professional.orders.delivery-attachments.download', [$order, $attachment]) }}">{{ $attachment->original_name }}</a></li>
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
            <p class="muted">Aucun événement opérationnel supplémentaire pour le moment.</p>
        @endforelse
    </section>

@endsection
