@extends('layouts.client')

@section('title', 'Détail du devis')

@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
@endpush

@push('scripts')
    @vite('resources/js/pages/client/favorites-requests-quotes.js')
@endpush

@section('content')
    @php
        $offer = $quotation->currentOffer;
        $activeStatus = in_array($quotation->status, [\App\Enums\QuotationStatus::SENT, \App\Enums\QuotationStatus::NEGOTIATING], true);
        $offerIsValid = $offer?->valid_until?->isFuture() ?? false;
        $clientMustDecide = $activeStatus
            && $offerIsValid
            && $offer?->actor_type === \App\Enums\QuotationOfferActor::PROFESSIONAL;
        $acceptedOffer = $quotation->acceptedOffer;
    @endphp

    <div class="client-module client-quote-detail">
        <header class="client-module__header">
            <div>
                <a class="client-back-link" href="{{ route('client.quotes.index') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à mes devis
                </a>
                <p class="client-module__eyebrow">DEVIS-{{ str_pad((string) $quotation->id, 6, '0', STR_PAD_LEFT) }}</p>
                <h1>{{ $quotation->serviceRequest?->service?->title ?? $quotation->serviceRequest?->title ?? 'Proposition commerciale' }}</h1>
                <p>Proposé par {{ $quotation->serviceRequest?->professional?->display_name ?: $quotation->serviceRequest?->professional?->user?->name ?: 'un professionnel PROXIWORK' }}</p>
            </div>
            <span class="status-badge status-badge--{{ $quotation->status->value }}">{{ $quotation->status->label() }}</span>
        </header>

        @if (session('success'))
            <x-alert type="success" :message="session('success')" />
        @endif
        @if (session('warning'))
            <x-alert type="warning" :message="session('warning')" />
        @endif
        @if ($errors->any())
            <div class="client-form-alert" role="alert" tabindex="-1">
                <strong>La décision n'a pas été enregistrée.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="client-quote-detail-grid">
            <main class="client-module">
                <section class="client-panel client-quote-current-offer">
                    <div class="client-panel__header">
                        <div>
                            <p class="client-module__eyebrow">{{ $quotation->status === \App\Enums\QuotationStatus::ACCEPTED ? 'OFFRE ACCEPTÉE' : 'PROPOSITION ACTUELLE' }}</p>
                            <h2>Conditions commerciales</h2>
                        </div>
                        @if ($offer)
                            <span class="client-quote-version">Version {{ $offer->version }}</span>
                        @endif
                    </div>

                    @if ($offer)
                        <div class="client-quote-total">
                            <span>Montant total proposé</span>
                            <strong>{{ number_format((float) $offer->amount, 2, ',', ' ') }} <small>{{ $offer->currency }}</small></strong>
                            <p>Ce montant est celui de cette version du devis. Il n'inclut que les éléments enregistrés dans la proposition.</p>
                        </div>

                        <div class="client-quote-terms-grid">
                            <div>
                                <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Émise le</span>
                                <strong>{{ optional($offer->created_at)->format('d/m/Y à H:i') }}</strong>
                            </div>
                            <div>
                                <span><i class="fa-regular fa-clock" aria-hidden="true"></i> Valable jusqu'au</span>
                                <strong>{{ optional($offer->valid_until)->format('d/m/Y à H:i') }}</strong>
                            </div>
                            <div>
                                <span><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Délai de réalisation</span>
                                <strong>{{ $offer->duration_value }} {{ $offer->duration_unit->value }}</strong>
                            </div>
                            <div>
                                <span><i class="fa-solid fa-user" aria-hidden="true"></i> Proposition faite par</span>
                                <strong>{{ $offer->actor_type === \App\Enums\QuotationOfferActor::CLIENT ? 'Vous (client)' : 'Le professionnel' }}</strong>
                            </div>
                        </div>

                        <div class="client-quote-text-block">
                            <h3>Description et périmètre</h3>
                            <p>{{ $offer->description }}</p>
                        </div>
                        <div class="client-quote-text-block">
                            <h3>Conditions de réalisation</h3>
                            <p>{{ $offer->conditions ?: 'Aucune condition supplémentaire n’a été enregistrée pour cette proposition.' }}</p>
                        </div>
                    @else
                        <x-empty-state icon="fa-file-circle-question" title="Proposition indisponible"
                                       message="Aucune version de devis n'est actuellement associée à cette demande. Contactez le support si le problème persiste." />
                    @endif
                </section>

                <section class="client-panel">
                    <div class="client-panel__header">
                        <div>
                            <h2>Historique des propositions</h2>
                            <p>Chaque version est conservée pour que vous puissiez vérifier les conditions réellement proposées.</p>
                        </div>
                        <span class="client-quote-history-count">{{ $quotation->offers->count() }} version(s)</span>
                    </div>

                    @if ($quotation->offers->isEmpty())
                        <p class="client-quote-muted">Aucune proposition versionnée n'est disponible.</p>
                    @else
                        <ol class="client-quote-version-list">
                            @foreach ($quotation->offers as $version)
                                @php
                                    $isCurrentVersion = $offer && $version->getKey() === $offer->getKey();
                                    $isAcceptedVersion = $acceptedOffer && $version->getKey() === $acceptedOffer->getKey();
                                @endphp
                                <li class="{{ $isCurrentVersion ? 'is-current' : '' }}">
                                    <div class="client-quote-version-list__header">
                                        <div>
                                            <strong>Version {{ $version->version }}</strong>
                                            <span>{{ $version->actor_type === \App\Enums\QuotationOfferActor::CLIENT ? 'Contre-proposition du client' : 'Proposition du professionnel' }}</span>
                                        </div>
                                        <div class="client-quote-version-list__amount">
                                            <strong>{{ number_format((float) $version->amount, 2, ',', ' ') }} {{ $version->currency }}</strong>
                                            @if ($isAcceptedVersion)
                                                <span class="client-quote-version-state client-quote-version-state--accepted">Version acceptée</span>
                                            @elseif ($isCurrentVersion)
                                                <span class="client-quote-version-state">Version actuelle</span>
                                            @else
                                                <span class="client-quote-version-state client-quote-version-state--replaced">Remplacée</span>
                                            @endif
                                        </div>
                                    </div>
                                    <p>{{ $version->description }}</p>
                                    @if ($version->conditions)
                                        <p class="client-quote-version-list__conditions"><strong>Conditions :</strong> {{ $version->conditions }}</p>
                                    @endif
                                    <dl>
                                        <div><dt>Délai</dt><dd>{{ $version->duration_value }} {{ $version->duration_unit->value }}</dd></div>
                                        <div><dt>Valable jusqu'au</dt><dd>{{ optional($version->valid_until)->format('d/m/Y à H:i') }}</dd></div>
                                        <div><dt>Émise par</dt><dd>{{ $version->creator?->name ?? 'Utilisateur indisponible' }}</dd></div>
                                        <div><dt>Créée le</dt><dd>{{ optional($version->created_at)->format('d/m/Y à H:i') }}</dd></div>
                                    </dl>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                <section class="client-panel">
                    <h2>Historique des décisions</h2>
                    @if ($quotation->events->isEmpty())
                        <p class="client-quote-muted">Aucun événement enregistré pour le moment.</p>
                    @else
                        <ol class="client-timeline client-quote-event-list">
                            @foreach ($quotation->events as $event)
                                <li>
                                    <strong>
                                        @switch($event->type)
                                            @case('quote_created') Devis envoyé @break
                                            @case('counter_offer_created') Nouvelle proposition envoyée @break
                                            @case('offer_accepted') Devis accepté par le client @break
                                            @case('offer_accepted_by_professional') Contre-proposition acceptée par le professionnel @break
                                            @case('quote_rejected') Devis refusé par le client @break
                                            @case('counter_offer_rejected_by_professional') Contre-proposition refusée par le professionnel @break
                                            @case('quote_expired') Devis expiré @break
                                            @case('quotation_withdrawn') Devis retiré @break
                                            @default {{ str_replace('_', ' ', ucfirst($event->type)) }}
                                        @endswitch
                                    </strong>
                                    <span>{{ optional($event->created_at)->format('d/m/Y à H:i') }} · {{ $event->actor?->name ?? 'Système PROXIWORK' }}</span>
                                    @if ($event->offer_id)
                                        <p>Version {{ $event->metadata['version'] ?? $quotation->offers->firstWhere('id', $event->offer_id)?->version ?? '—' }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </main>

            <aside class="client-module client-quote-sidebar">
                <section class="client-panel">
                    <h2>Récapitulatif</h2>
                    <dl class="client-definition-list">
                        <div><dt>Référence</dt><dd>DEV-{{ str_pad((string) $quotation->id, 6, '0', STR_PAD_LEFT) }}</dd></div>
                        <div><dt>Service</dt><dd>{{ $quotation->serviceRequest?->service?->title ?? $quotation->serviceRequest?->title ?? 'Prestation' }}</dd></div>
                        <div><dt>Professionnel</dt><dd>{{ $quotation->serviceRequest?->professional?->display_name ?: $quotation->serviceRequest?->professional?->user?->name ?: 'Non disponible' }}</dd></div>
                        <div><dt>Statut du devis</dt><dd>{{ $quotation->status->label() }}</dd></div>
                    </dl>
                    <a class="btn btn-secondary client-quote-full-width" href="{{ route('client.requests.show', $quotation->serviceRequest) }}">
                        <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Voir la demande
                    </a>
                </section>

                @if ($quotation->status === \App\Enums\QuotationStatus::ACCEPTED)
                    <section class="client-panel client-quote-order-panel">
                        <span class="client-quote-order-icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span>
                        <h2>Devis accepté</h2>
                        <p>L'acceptation commerciale ne signifie pas que le paiement est effectué.</p>
                        @if ($quotation->order)
                            <dl class="client-definition-list">
                                <div><dt>Commande</dt><dd>{{ $quotation->order->order_number }}</dd></div>
                                <div><dt>État</dt><dd>{{ $quotation->order->status->value }}</dd></div>
                                <div><dt>Total contractuel</dt><dd>{{ number_format((float) $quotation->order->total, 2, ',', ' ') }} {{ $quotation->order->currency }}</dd></div>
                            </dl>
                            <a class="btn btn-primary client-quote-full-width" href="{{ route('client.orders.show', $quotation->order) }}">Ouvrir la commande</a>
                            @if ($quotation->order->status === \App\Enums\OrderStatus::PENDING_PAYMENT)
                                <a class="client-quote-secondary-link" href="{{ route('client.orders.payment', $quotation->order) }}">Continuer vers le paiement</a>
                            @endif
                        @else
                            <p class="client-form-alert" role="status">La commande associée n'est pas visible. Contactez le support avant toute nouvelle action.</p>
                        @endif
                    </section>
                @endif

                @if ($activeStatus && ! $offerIsValid)
                    <section class="client-form-alert" role="status">
                        <strong>Cette proposition a expiré.</strong>
                        <p>Vous ne pouvez plus l'accepter ni la négocier. Le professionnel doit envoyer une nouvelle proposition valide.</p>
                    </section>
                @elseif ($activeStatus && $offer?->actor_type === \App\Enums\QuotationOfferActor::CLIENT)
                    <section class="client-form-note" role="status">
                        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                        <div><strong>En attente du professionnel</strong><p>Votre dernière proposition est enregistrée. Vous pourrez décider lorsque le professionnel aura répondu.</p></div>
                    </section>
                @elseif ($clientMustDecide)
                    <section class="client-panel client-quote-decision-panel">
                        <h2>Votre décision</h2>
                        <p>Vérifiez le montant, la devise, le délai et les conditions avant de répondre.</p>
                        @if (! $quotation->serviceRequest?->address)
                            <div class="client-form-alert">
                                <strong>Adresse d'exécution requise</strong>
                                <p>Une adresse appartenant à votre compte est nécessaire pour créer la commande après acceptation.</p>
                                <a class="btn btn-secondary client-quote-full-width" href="{{ route('client.requests.show', $quotation->serviceRequest) }}">Choisir l'adresse</a>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('client.quotes.accept', $quotation) }}">
                            @csrf
                            <button class="btn btn-primary client-quote-full-width" type="submit"
                                @disabled(! $quotation->serviceRequest?->address)
                                data-confirm-submit="Accepter {{ number_format((float) $offer->amount, 2, ',', ' ') }} {{ $offer->currency }} et ses conditions ? Cette décision créera une commande à l'étape prévue par PROXIWORK ; le paiement n'est pas encore confirmé.">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Accepter le devis
                            </button>
                        </form>
                        <form method="POST" action="{{ route('client.quotes.reject', $quotation) }}">
                            @csrf
                            <button class="btn btn-danger client-quote-full-width" type="submit"
                                data-confirm-submit="Confirmer le refus de la version {{ $offer->version }} ? Cette décision sera enregistrée dans l'historique.">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i> Refuser le devis
                            </button>
                        </form>
                    </section>

                    <section class="client-panel client-quote-counter-panel">
                        <h2>Demander une modification</h2>
                        <p>Votre réponse crée une nouvelle version ; les versions précédentes restent consultables. La devise reste identique pendant la négociation.</p>
                        <form class="client-form-grid" method="POST" action="{{ route('client.quotes.counter-offer', $quotation) }}">
                            @csrf
                            <input type="hidden" name="currency" value="{{ $offer->currency }}">
                            <div class="client-field">
                                <label for="counter-amount">Montant proposé ({{ $offer->currency }})</label>
                                <input id="counter-amount" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99"
                                       value="{{ old('amount') }}" required>
                                @error('amount')<small class="client-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="client-field">
                                <label for="counter-currency-display">Devise</label>
                                <input id="counter-currency-display" value="{{ $offer->currency }}" readonly aria-readonly="true">
                            </div>
                            <div class="client-field">
                                <label for="counter-duration">Délai proposé</label>
                                <input id="counter-duration" name="duration_value" type="number" min="1" max="10000"
                                       value="{{ old('duration_value', $offer->duration_value) }}" required>
                                @error('duration_value')<small class="client-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="client-field">
                                <label for="counter-duration-unit">Unité</label>
                                <select id="counter-duration-unit" name="duration_unit" required>
                                    @foreach (\App\Enums\QuotationDurationUnit::cases() as $unit)
                                        <option value="{{ $unit->value }}" @selected(old('duration_unit', $offer->duration_unit->value) === $unit->value)>{{ ucfirst($unit->value) }}</option>
                                    @endforeach
                                </select>
                                @error('duration_unit')<small class="client-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="client-field client-form-grid__full">
                                <label for="counter-valid-until">Validité de votre proposition</label>
                                <input id="counter-valid-until" name="valid_until" type="datetime-local"
                                       value="{{ old('valid_until') }}" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}" required>
                                @error('valid_until')<small class="client-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="client-field client-form-grid__full">
                                <label for="counter-description">Description et périmètre</label>
                                <textarea id="counter-description" name="description" rows="4" minlength="10" maxlength="10000" required>{{ old('description', $offer->description) }}</textarea>
                                @error('description')<small class="client-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="client-field client-form-grid__full">
                                <label for="counter-conditions">Conditions complémentaires (facultatif)</label>
                                <textarea id="counter-conditions" name="conditions" rows="3" maxlength="10000">{{ old('conditions', $offer->conditions) }}</textarea>
                                @error('conditions')<small class="client-field-error">{{ $message }}</small>@enderror
                            </div>
                            <div class="client-form-grid__full">
                                <button class="btn btn-secondary client-quote-full-width" type="submit"
                                    data-confirm-submit="Envoyer cette nouvelle version au professionnel ? La proposition actuelle restera dans l'historique.">
                                    Envoyer ma contre-proposition
                                </button>
                            </div>
                        </form>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
