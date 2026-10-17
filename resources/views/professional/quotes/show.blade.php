@extends('layouts.professional')

@section('title', 'Devis #' . $quotation->getKey() . ' — PROXIWORK')
@section('page_heading', 'Détail du devis')

@push('head')
    @vite('resources/css/pages/professional/quotes.css')
@endpush

@push('scripts')
    @vite('resources/js/pages/client/favorites-requests-quotes.js')
@endpush

@section('content')
    @php
        $offer = $quotation->currentOffer;
        $activeStatus = in_array($quotation->status, [\App\Enums\QuotationStatus::SENT, \App\Enums\QuotationStatus::NEGOTIATING], true);
        $offerIsValid = $offer?->valid_until?->isFuture() ?? false;
        $professionalMustDecide = $activeStatus
            && $offerIsValid
            && $offer?->actor_type === \App\Enums\QuotationOfferActor::CLIENT;
    @endphp

    <div class="professional-quote-page">
        <div class="dashboard-page-header">
            <div>
                <p class="eyebrow">DEVIS-{{ str_pad((string) $quotation->getKey(), 6, '0', STR_PAD_LEFT) }}</p>
                <h1>{{ $quotation->serviceRequest?->title ?? $quotation->serviceRequest?->service?->title ?? 'Demande de service' }}</h1>
                <p class="page-lead">Client : {{ $quotation->serviceRequest?->client?->name ?? 'Client PROXIWORK' }}</p>
                <p class="page-lead">Statut : {{ $quotation->status->label() }}</p>
            </div>
            <div class="dashboard-page-header__actions">
                <a class="button button--ghost" href="{{ route('professional.quotes.index') }}">Retour aux devis</a>
                <a class="button button--ghost" href="{{ route('professional.requests.show', $quotation->serviceRequest) }}">Voir la demande</a>
            </div>
        </div>

        @if (session('success'))
            <div class="professional-quote-alert professional-quote-alert--success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="professional-quote-alert" role="alert">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="professional-quote-alert" role="alert">
                <strong>La décision n'a pas été enregistrée.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="surface-card content-card professional-quote-panel">
            <div class="professional-quote-panel__header">
                <div>
                    <p class="eyebrow">{{ $quotation->status === \App\Enums\QuotationStatus::ACCEPTED ? 'OFFRE ACCEPTÉE' : 'PROPOSITION ACTIVE' }}</p>
                    <h2>Conditions commerciales</h2>
                </div>
                @if ($offer)<span class="professional-quote-version">Version {{ $offer->version }}</span>@endif
            </div>

            @if ($offer)
                <div class="professional-quote-total">
                    <span>Montant proposé</span>
                    <strong>{{ number_format((float) $offer->amount, 2, ',', ' ') }} {{ $offer->currency }}</strong>
                </div>
                <dl class="details-list">
                    <div><dt>Émise par</dt><dd>{{ $offer->actor_type === \App\Enums\QuotationOfferActor::CLIENT ? 'Le client' : 'Vous' }}</dd></div>
                    <div><dt>Délai proposé</dt><dd>{{ $offer->duration_value }} {{ match($offer->duration_unit->value) { 'hours' => 'heure(s)', 'days' => 'jour(s)', 'weeks' => 'semaine(s)', default => $offer->duration_unit->value } }}</dd></div>
                    <div><dt>Valable jusqu'au</dt><dd>{{ $offer->valid_until?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                    <div><dt>Créée le</dt><dd>{{ $offer->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
                    <div><dt>Description et périmètre</dt><dd class="professional-quote-preline">{{ $offer->description }}</dd></div>
                    <div><dt>Conditions</dt><dd class="professional-quote-preline">{{ $offer->conditions ?: 'Aucune condition supplémentaire enregistrée.' }}</dd></div>
                </dl>
            @else
                <p>Aucune offre courante n'est disponible.</p>
            @endif
        </section>

        @if ($activeStatus && ! $offerIsValid)
            <div class="professional-quote-alert" role="status">
                <strong>La proposition active a expiré.</strong>
                <p>Elle ne peut plus être acceptée ni négociée. Un renouvellement explicite est nécessaire.</p>
            </div>
        @elseif ($activeStatus && $offer?->actor_type === \App\Enums\QuotationOfferActor::PROFESSIONAL)
            <div class="professional-quote-note" role="status">
                <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                <div><strong>En attente de la décision du client</strong><p>Votre proposition est active. Le client peut l'accepter, la refuser ou envoyer une contre-proposition.</p></div>
            </div>
        @elseif ($professionalMustDecide)
            <section class="surface-card content-card professional-quote-panel">
                <h2>Répondre à la contre-proposition du client</h2>
                <p>Vous pouvez accepter les conditions exactes proposées, refuser cette contre-proposition ou envoyer une nouvelle version. Toute décision sera historisée.</p>

                <div class="professional-quote-actions">
                    <form method="POST" action="{{ route('professional.quotes.accept-client-offer', $quotation) }}">
                        @csrf
                        <button class="button button--primary" type="submit"
                            data-confirm-submit="Accepter la contre-proposition de {{ number_format((float) $offer->amount, 2, ',', ' ') }} {{ $offer->currency }} ? Une commande sera créée et le paiement restera une étape distincte.">
                            Accepter la contre-proposition
                        </button>
                    </form>
                    <form method="POST" action="{{ route('professional.quotes.reject-client-offer', $quotation) }}">
                        @csrf
                        <button class="button button--danger" type="submit"
                            data-confirm-submit="Refuser la contre-proposition du client ? Le devis sera clôturé et cette décision sera enregistrée.">
                            Refuser la contre-proposition
                        </button>
                    </form>
                </div>

                <div class="professional-quote-counter">
                    <h3>Envoyer une nouvelle proposition</h3>
                    <p>La devise reste {{ $offer->currency }} pendant cette négociation. La version actuelle restera dans l'historique.</p>
                    <form method="POST" action="{{ route('professional.quotes.counter-offer', $quotation) }}" class="professional-quote-form">
                        @csrf
                        <input type="hidden" name="currency" value="{{ $offer->currency }}">
                        <div class="field">
                            <label for="professional-counter-amount">Montant proposé ({{ $offer->currency }})</label>
                            <input id="professional-counter-amount" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" value="{{ old('amount', $offer->amount) }}" required>
                            @error('amount')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="field">
                            <label for="professional-counter-duration">Délai</label>
                            <input id="professional-counter-duration" name="duration_value" type="number" min="1" max="10000" value="{{ old('duration_value', $offer->duration_value) }}" required>
                            @error('duration_value')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="field">
                            <label for="professional-counter-unit">Unité</label>
                            <select id="professional-counter-unit" name="duration_unit" required>
                                @foreach (\App\Enums\QuotationDurationUnit::cases() as $unit)
                                    <option value="{{ $unit->value }}" @selected(old('duration_unit', $offer->duration_unit->value) === $unit->value)>{{ match($unit->value) { 'hours' => 'Heures', 'days' => 'Jours', 'weeks' => 'Semaines', default => ucfirst($unit->value) } }}</option>
                                @endforeach
                            </select>
                            @error('duration_unit')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="field">
                            <label for="professional-counter-valid-until">Valable jusqu'au</label>
                            <input id="professional-counter-valid-until" name="valid_until" type="datetime-local" value="{{ old('valid_until') }}" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}" required>
                            @error('valid_until')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="field professional-quote-form__full">
                            <label for="professional-counter-description">Description et périmètre</label>
                            <textarea id="professional-counter-description" name="description" rows="4" minlength="10" maxlength="10000" required>{{ old('description', $offer->description) }}</textarea>
                            @error('description')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="field professional-quote-form__full">
                            <label for="professional-counter-conditions">Conditions complémentaires (facultatif)</label>
                            <textarea id="professional-counter-conditions" name="conditions" rows="3" maxlength="10000">{{ old('conditions', $offer->conditions) }}</textarea>
                            @error('conditions')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="professional-quote-form__full">
                            <button class="button button--primary" type="submit"
                                data-confirm-submit="Envoyer cette nouvelle version au client ? La proposition actuelle restera dans l'historique.">
                                Envoyer la nouvelle proposition
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        @endif

        @if ($quotation->status === \App\Enums\QuotationStatus::EXPIRED && $offer)
            <section class="surface-card content-card professional-quote-panel">
                <h2>Renouveler le devis expiré</h2>
                <p>Le devis précédent reste dans l’historique. Cette action crée une nouvelle version, avec une nouvelle date de validité, que le client devra examiner avant toute acceptation.</p>
                <form method="POST" action="{{ route('professional.quotes.renew', $quotation) }}" class="professional-quote-form">
                    @csrf
                    <input type="hidden" name="currency" value="{{ $offer->currency }}">
                    <div class="field">
                        <label for="renew-amount">Montant proposé ({{ $offer->currency }})</label>
                        <input id="renew-amount" name="amount" type="number" step="0.01" min="0.01" max="9999999999.99" value="{{ old('amount', $offer->amount) }}" required>
                        @error('amount')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="renew-duration">Délai proposé</label>
                        <input id="renew-duration" name="duration_value" type="number" min="1" max="10000" value="{{ old('duration_value', $offer->duration_value) }}" required>
                        @error('duration_value')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="renew-duration-unit">Unité</label>
                        <select id="renew-duration-unit" name="duration_unit" required>
                            @foreach (\App\Enums\QuotationDurationUnit::cases() as $unit)
                                <option value="{{ $unit->value }}" @selected(old('duration_unit', $offer->duration_unit->value) === $unit->value)>{{ ucfirst($unit->value) }}</option>
                            @endforeach
                        </select>
                        @error('duration_unit')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field">
                        <label for="renew-valid-until">Nouvelle date limite</label>
                        <input id="renew-valid-until" name="valid_until" type="datetime-local" value="{{ old('valid_until') }}" min="{{ now()->addMinute()->format('Y-m-d\\TH:i') }}" required>
                        @error('valid_until')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field professional-quote-form__full">
                        <label for="renew-description">Description et périmètre</label>
                        <textarea id="renew-description" name="description" rows="4" minlength="10" maxlength="10000" required>{{ old('description', $offer->description) }}</textarea>
                        @error('description')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="field professional-quote-form__full">
                        <label for="renew-conditions">Conditions complémentaires (facultatif)</label>
                        <textarea id="renew-conditions" name="conditions" rows="3" maxlength="10000">{{ old('conditions', $offer->conditions) }}</textarea>
                        @error('conditions')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="professional-quote-form__full">
                        <button class="button button--primary" type="submit"
                            data-confirm-submit="Créer une nouvelle version du devis avec une validité renouvelée ? L’ancienne proposition restera consultable et ne sera pas réactivée.">
                            Renouveler le devis
                        </button>
                    </div>
                </form>
            </section>
        @endif

        @if ($quotation->status === \App\Enums\QuotationStatus::ACCEPTED && $quotation->order)
            <section class="surface-card content-card professional-quote-panel">
                <h2>Commande issue du devis</h2>
                <dl class="details-list">
                    <div><dt>Référence</dt><dd>{{ $quotation->order->order_number }}</dd></div>
                    <div><dt>Statut</dt><dd>{{ $quotation->order->status->value }}</dd></div>
                    <div><dt>Total contractuel</dt><dd>{{ number_format((float) $quotation->order->total, 2, ',', ' ') }} {{ $quotation->order->currency }}</dd></div>
                </dl>
                <a class="button button--primary" href="{{ route('professional.orders.show', $quotation->order) }}">Ouvrir la commande</a>
            </section>
        @endif

        <section class="surface-card content-card professional-quote-panel">
            <h2>Historique des versions</h2>
            @forelse ($quotation->offers as $version)
                <article class="professional-quote-version {{ $offer && $version->getKey() === $offer->getKey() ? 'is-current' : '' }}">
                    <div class="professional-quote-version__header">
                        <div>
                            <h3>Version {{ $version->version }} · {{ $version->actor_type === \App\Enums\QuotationOfferActor::CLIENT ? 'Client' : 'Professionnel' }}</h3>
                            <p>{{ $version->created_at?->format('d/m/Y à H:i') }} · valable jusqu'au {{ $version->valid_until?->format('d/m/Y à H:i') }}</p>
                        </div>
                        <strong>{{ number_format((float) $version->amount, 2, ',', ' ') }} {{ $version->currency }}</strong>
                    </div>
                    <p class="professional-quote-preline">{{ $version->description }}</p>
                    @if ($quotation->accepted_offer_id === $version->getKey())
                        <span class="professional-quote-version__badge">Version acceptée</span>
                    @elseif ($offer && $version->getKey() === $offer->getKey())
                        <span class="professional-quote-version__badge">Version active</span>
                    @else
                        <span class="professional-quote-version__badge professional-quote-version__badge--muted">Version remplacée</span>
                    @endif
                </article>
            @empty
                <p>Aucune version historique.</p>
            @endforelse
        </section>

        <section class="surface-card content-card professional-quote-panel">
            <h2>Historique des décisions</h2>
            @forelse ($quotation->events as $event)
                <article class="timeline-item">
                    <h3>
                        @switch($event->type)
                            @case('quote_created') Devis envoyé @break
                            @case('counter_offer_created') Nouvelle proposition envoyée @break
                            @case('offer_accepted') Devis accepté par le client @break
                            @case('offer_accepted_by_professional') Contre-proposition acceptée par le professionnel @break
                            @case('quote_rejected') Devis refusé par le client @break
                            @case('counter_offer_rejected_by_professional') Contre-proposition refusée par le professionnel @break
                            @case('quote_expired') Devis expiré @break
                            @default {{ str_replace('_', ' ', ucfirst($event->type)) }}
                        @endswitch
                    </h3>
                    <p class="table-secondary">{{ $event->created_at?->format('d/m/Y à H:i') }} · {{ $event->actor?->name ?? 'Système PROXIWORK' }}</p>
                </article>
            @empty
                <p>Aucun événement enregistré.</p>
            @endforelse
        </section>
    </div>
@endsection
