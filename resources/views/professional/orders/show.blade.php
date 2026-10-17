@extends('layouts.professional')

@section('title', 'Commande #'.$order->id)

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/orders-revenues.css', 'resources/js/pages/professional/orders-revenues.js'])
    @endpush
    @push('scripts')
        @vite('resources/js/pages/client/favorites-requests-quotes.js')
    @endpush
    @php
        $orderStatusLabel = match ($order->status->value) {
            'pending_payment' => 'En attente de paiement',
            'confirmed' => 'Confirmée / à planifier',
            'in_progress' => 'En cours',
            'delivery_submitted' => 'Livrable à vérifier',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
            'disputed' => 'Contestée',
            default => ucfirst(str_replace('_', ' ', $order->status->value)),
        };
        $paymentStatusLabel = match ($order->payment?->status?->value) {
            'initiated' => 'Initialisé',
            'pending' => 'En attente',
            'processing' => 'En cours de traitement',
            'succeeded' => 'Réussi',
            'failed' => 'Échec',
            'cancelled' => 'Annulé',
            'expired' => 'Expiré',
            'refunded' => 'Remboursé',
            default => 'Non disponible',
        };
        $requiresClientAddress = $order->serviceRequest?->execution_mode?->requiresClientAddress() ?? false;
    @endphp
    <div class="page-header professional-order-detail-header">
        <div>
            <p class="page-kicker">SUIVI DE PRESTATION · {{ $order->order_number ?? ('COMMANDE #'.$order->getKey()) }}</p>
            <h1>Détail de la commande</h1>
            <p class="page-description">{{ $order->serviceRequest?->title ?? 'Prestation' }} · Client : {{ $order->client?->name ?? 'Client' }}</p>
        </div>
        <span class="status-badge status-badge--{{ $order->status->value }}" role="status">{{ $orderStatusLabel }}</span>
    </div>

    <div class="detail-grid">
        <section class="data-card">
            <h2>Commande</h2>
            <dl class="detail-list">
                <div><dt>Référence</dt><dd>{{ $order->order_number ?? ('#'.$order->getKey()) }}</dd></div>
                <div><dt>Client</dt><dd>{{ $order->client?->name ?? '—' }}</dd></div>
                <div><dt>Créée le</dt><dd>{{ $order->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                <div><dt>Date souhaitée par le client</dt><dd>{{ $order->serviceRequest?->desired_at?->format('d/m/Y à H:i') ?? 'À convenir' }}</dd></div>
                <div><dt>Total contractuel</dt><dd>{{ number_format((float) $order->total, 2, ',', ' ') }} {{ $order->currency ?? '' }}</dd></div>
                <div><dt>Service</dt><dd>{{ $order->serviceRequest?->service?->title ?? '—' }}</dd></div>
            </dl>
            @if (filled($order->acceptedOffer?->description))
                <div class="professional-order-contract-scope">
                    <h3>Périmètre accepté</h3>
                    <p>{{ $order->acceptedOffer->description }}</p>
                    @if (filled($order->acceptedOffer->conditions))
                        <h4>Conditions convenues</h4>
                        <p>{{ $order->acceptedOffer->conditions }}</p>
                    @endif
                </div>
            @endif
        </section>

        <section class="data-card">
            <h2>Finances</h2>
            <dl class="detail-list">
                <div><dt>État du paiement</dt><dd>{{ $paymentStatusLabel }}</dd></div>
                <div><dt>Revenu brut</dt><dd>{{ $order->commission ? number_format((float) $order->commission->gross_amount, 2, ',', ' ').' '.$order->commission->currency : '—' }}</dd></div>
                <div><dt>Commission PROXIWORK</dt><dd>{{ $order->commission ? number_format((float) $order->commission->commission_amount, 2, ',', ' ').' '.$order->commission->currency : '—' }}</dd></div>
                <div><dt>Revenu net professionnel</dt><dd>{{ $order->commission ? number_format((float) $order->commission->net_amount, 2, ',', ' ').' '.$order->commission->currency : '—' }}</dd></div>
            </dl>
        </section>

        <section class="data-card professional-order-history-card">
            <h2>Historique des statuts</h2>
            <ol class="professional-order-status-timeline">
                @forelse ($order->statusHistories as $history)
                    @php($historyStatusValue = $history->to_status?->value ?? $history->to_status)
                    <li>
                        <span class="professional-order-status-timeline__marker" aria-hidden="true"></span>
                        <div>
                            <strong>{{ match($historyStatusValue) { 'pending_payment' => 'En attente de paiement', 'confirmed' => 'Commande confirmée', 'in_progress' => 'Prestation en cours', 'delivery_submitted' => 'Livrable soumis', 'completed' => 'Commande terminée', 'cancelled' => 'Commande annulée', 'disputed' => 'Commande contestée', default => ucfirst(str_replace('_', ' ', (string) $historyStatusValue)) } }}</strong>
                            <time datetime="{{ $history->created_at?->toIso8601String() }}">{{ $history->created_at?->format('d/m/Y à H:i') ?? 'Date indisponible' }}</time>
                            @if (filled($history->reason))
                                <p>{{ $history->reason }}</p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li><div><strong>{{ $orderStatusLabel }}</strong><p class="muted">Aucun historique complémentaire enregistré.</p></div></li>
                @endforelse
            </ol>
        </section>

        <section class="data-card">
            <h2>Lieu d’exécution</h2>
            @if ($order->serviceRequest?->execution_mode?->requiresClientAddress())
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
                    <p class="muted">Aucun snapshot d’adresse disponible.</p>
                @endif
            @else
                <p class="muted">Prestation à distance ou sans adresse client requise : aucune adresse physique n’est affichée.</p>
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
        $latestCorrection = $order->statusHistories->last(
            fn ($history) => data_get($history->metadata, 'event') === 'delivery_changes_requested'
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
        @elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED)
            @if (data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_proposed')
                <div class="professional-order-workflow__notice" role="status">
                    <strong>Proposition de planning envoyée</strong>
                    <p>Le client doit confirmer les dates ou demander une modification avant qu’une nouvelle proposition soit possible.</p>
                    <p>Début proposé : {{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_start_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                    <p>Fin prévue : {{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_end_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                    @if (filled(data_get($latestScheduleEvent->metadata, 'note')))<p>{{ data_get($latestScheduleEvent->metadata, 'note') }}</p>@endif
                </div>
            @else
                @if (data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_change_requested')
                    <div class="professional-order-workflow__notice professional-order-workflow__notice--attention" role="status">
                        <strong>Le client a demandé une modification du planning</strong>
                        @if (filled(data_get($latestScheduleEvent->metadata, 'reason')))
                            <p><strong>Motif :</strong> {{ data_get($latestScheduleEvent->metadata, 'reason') }}</p>
                        @endif
                        @if (filled(data_get($latestScheduleEvent->metadata, 'previous_start_at')))
                            <p>Début précédent : {{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'previous_start_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                        @endif
                        @if (filled(data_get($latestScheduleEvent->metadata, 'previous_end_at')))
                            <p>Fin précédente : {{ \Carbon\CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'previous_end_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                        @endif
                        <p>Envoyez une nouvelle proposition après avoir tenu compte de cette demande.</p>
                    </div>
                @endif
                <form method="POST" action="{{ route('professional.orders.schedule', $order) }}" class="professional-order-schedule-form" data-schedule-form>
                    @csrf
                    <h3>{{ data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_change_requested' ? 'Proposer un nouveau planning' : 'Proposer un planning au client' }}</h3>
                    <p>Les dates restent proposées tant que le client ne les a pas confirmées. La date souhaitée par le client n’est pas automatiquement un engagement.</p>
                    <label for="schedule-starts-at">Début proposé</label>
                    <input id="schedule-starts-at" name="starts_at" type="datetime-local" min="{{ now()->format('Y-m-d\\TH:i') }}" value="{{ old('starts_at') }}" required aria-describedby="schedule-starts-at-help">
                    <small id="schedule-starts-at-help" class="muted">Choisissez une date future selon le fuseau horaire configuré par PROXIWORK.</small>
                    @error('starts_at')<small class="field-error">{{ $message }}</small>@enderror
                    <label for="schedule-ends-at">Fin prévue</label>
                    <input id="schedule-ends-at" name="ends_at" type="datetime-local" min="{{ old('starts_at', now()->format('Y-m-d\\TH:i')) }}" value="{{ old('ends_at') }}" required aria-describedby="schedule-ends-at-help">
                    <small id="schedule-ends-at-help" class="muted">La fin doit être postérieure au début proposé.</small>
                    @error('ends_at')<small class="field-error">{{ $message }}</small>@enderror
                    <label for="schedule-note">Note (facultative)</label>
                    <textarea id="schedule-note" name="note" rows="2" maxlength="1000" aria-describedby="schedule-note-help">{{ old('note') }}</textarea>
                    <small id="schedule-note-help" class="muted">1 000 caractères maximum.</small>
                    @error('note')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="button button--secondary" type="submit" data-confirm-submit="Envoyer cette proposition de planning au client ?">Envoyer la proposition</button>
                </form>
            @endif
        @endif

        @if ($order->status === \App\Enums\OrderStatus::PENDING_PAYMENT)
            <div class="professional-order-workflow__notice" role="status">
                <strong>En attente de paiement</strong>
                <p>Le démarrage est désactivé jusqu’à la confirmation du paiement par PROXIWORK.</p>
            </div>
        @elseif ($order->status === \App\Enums\OrderStatus::CONFIRMED)
            @if (data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_proposed')
                <div class="professional-order-workflow__notice" role="status">
                    <strong>Confirmation du planning attendue</strong>
                    <p>Le démarrage est indisponible tant que le client n’a pas confirmé les dates proposées ou demandé une modification.</p>
                </div>
            @elseif (data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_change_requested')
                <div class="professional-order-workflow__notice professional-order-workflow__notice--attention" role="status">
                    <strong>Planning à réviser</strong>
                    <p>Le client a demandé une modification. Envoyez une nouvelle proposition et attendez sa résolution avant de démarrer.</p>
                </div>
            @elseif ($order->schedule_confirmed_at && $order->scheduled_start_at?->isFuture())
                <div class="professional-order-workflow__notice" role="status">
                    <strong>Le planning est confirmé</strong>
                    <p>Le démarrage sera possible à partir du {{ $order->scheduled_start_at->format('d/m/Y à H:i') }}. La commande reste confirmée jusque-là.</p>
                </div>
            @else
                <form method="POST" action="{{ route('professional.orders.start', $order) }}">
                    @csrf
                    <button class="button button--primary" type="submit" data-confirm-submit="Confirmer le démarrage de cette prestation ?">Démarrer la prestation</button>
                </form>
            @endif
        @elseif ($order->status === \App\Enums\OrderStatus::IN_PROGRESS)
            <div class="professional-order-workflow__forms">
                <form method="POST" enctype="multipart/form-data" action="{{ route('professional.orders.submit-delivery', $order) }}">
                    @csrf
                    @if (data_get($latestCorrection?->metadata, 'event') === 'delivery_changes_requested')
                        <div class="professional-order-workflow__notice professional-order-workflow__notice--attention" role="status">
                            <strong>Correction demandée par le client</strong>
                            @if (filled(data_get($latestCorrection->metadata, 'reason')))
                                <p><strong>Retour du client :</strong> {{ data_get($latestCorrection->metadata, 'reason') }}</p>
                            @endif
                            <p>Présentez les changements apportés et transmettez une nouvelle version. La livraison précédente reste dans l’historique.</p>
                        </div>
                    @endif
                    <h3>{{ data_get($latestCorrection?->metadata, 'event') === 'delivery_changes_requested' ? 'Soumettre la version corrigée' : 'Soumettre le résultat au client' }}</h3>
                    <p>Décrivez le travail effectué, les éléments remis et les consignes utiles au client. Le livrable restera en attente de réception jusqu’à sa décision.</p>
                    <label for="delivery-summary">Résumé du livrable</label>
                    <textarea id="delivery-summary" name="summary" rows="5" minlength="20" maxlength="5000" required aria-describedby="delivery-summary-count">{{ old('summary') }}</textarea>
                    <small id="delivery-summary-count" class="professional-order-char-count" data-char-count-for="delivery-summary" aria-live="polite">0 / 5000 caractères</small>
                    @error('summary')<small class="field-error">{{ $message }}</small>@enderror
                    <label for="delivery-attachments">Fichiers livrés (facultatif, 5 fichiers maximum)</label>
                    <input id="delivery-attachments" type="file" name="attachments[]" multiple
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip"
                        aria-describedby="delivery-attachments-help delivery-attachments-error" data-delivery-files>
                    <small id="delivery-attachments-help" class="muted">5 fichiers maximum, 10 Mo par fichier. Formats acceptés : PDF, JPG/PNG, DOC/DOCX, XLS/XLSX et ZIP. Le serveur effectue la validation définitive.</small>
                    <p id="delivery-attachments-error" class="field-error" data-delivery-file-error role="alert" hidden></p>
                    <ul class="professional-order-file-preview" data-delivery-file-list aria-live="polite"></ul>
                    @error('attachments')<small class="field-error">{{ $message }}</small>@enderror
                    @error('attachments.*')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="button button--primary" type="submit" data-confirm-submit="Soumettre cette livraison au client pour vérification ? Le client devra ensuite confirmer la réception ou demander une correction." data-loading-label="Envoi de la livraison…">Soumettre pour réception</button>
                </form>

                <form method="POST" action="{{ route('professional.orders.report-blocker', $order) }}">
                    @csrf
                    <h3>Signaler une difficulté</h3>
                    <label for="blocker-description">Décrivez le blocage et la prochaine étape proposée</label>
                    <textarea id="blocker-description" name="description" rows="3" minlength="10" maxlength="2000" required aria-describedby="blocker-description-count">{{ old('description') }}</textarea>
                    <small id="blocker-description-count" class="professional-order-char-count" data-char-count-for="blocker-description" aria-live="polite">0 / 2000 caractères</small>
                    @error('description')<small class="field-error">{{ $message }}</small>@enderror
                    <button class="button button--secondary" type="submit" data-confirm-submit="Signaler cette difficulté au client ?">Informer le client</button>
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
                <p>Le client a confirmé la réception de la prestation. Aucun nouvel envoi n’est nécessaire pour cette commande terminée.</p>
            </div>
        @elseif ($order->status === \App\Enums\OrderStatus::DISPUTED)
            <div class="professional-order-workflow__notice professional-order-workflow__notice--attention" role="status">
                <strong>Commande contestée</strong>
                <p>La commande nécessite une résolution. Les actions de démarrage, de livraison et de clôture ne sont pas proposées dans cet état.</p>
            </div>
        @elseif ($order->status === \App\Enums\OrderStatus::CANCELLED)
            <div class="professional-order-workflow__notice" role="status">
                <strong>Commande annulée</strong>
                <p>Le workflow opérationnel de cette commande est terminé. Consultez l’historique ci-dessous pour retrouver les événements enregistrés.</p>
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
                @elseif (data_get($event->metadata, 'event') === 'schedule_change_requested')
                    @if (filled(data_get($event->metadata, 'previous_start_at')))
                        <p>Début précédent : {{ \Carbon\CarbonImmutable::parse(data_get($event->metadata, 'previous_start_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                    @endif
                    @if (filled(data_get($event->metadata, 'previous_end_at')))
                        <p>Fin précédente : {{ \Carbon\CarbonImmutable::parse(data_get($event->metadata, 'previous_end_at'))->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</p>
                    @endif
                    @if (filled(data_get($event->metadata, 'reason')))<p><strong>Motif de modification :</strong> {{ data_get($event->metadata, 'reason') }}</p>@endif
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
