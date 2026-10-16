@extends('layouts.client')

@section('title', 'Mes devis')

@push('head')
    @vite('resources/css/pages/client/favorites-requests-quotes.css')
@endpush

@push('scripts')
    @vite('resources/js/pages/client/favorites-requests-quotes.js')
@endpush

@section('content')
    @php
        $activeBucket = $filters['bucket'] ?? '';
        $activeStatuses = \App\Enums\QuotationStatus::cases();
        $hasFilters = collect($filters)->contains(fn ($value, $key) => $key !== 'bucket' && $value !== null && $value !== '' && ! ($key === 'sort' && $value === 'newest')) || $activeBucket !== '';
    @endphp

    <div class="client-module client-quotes">
        <header class="client-module__header">
            <div>
                <p class="client-module__eyebrow">ESPACE COMMERCIAL</p>
                <h1>Mes devis</h1>
                <p>Examinez les propositions, comparez leurs conditions et prenez votre décision en toute clarté.</p>
            </div>
            <a class="btn btn-secondary" href="{{ route('client.requests.index') }}">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                Mes demandes
            </a>
        </header>

        @if (session('success'))
            <x-alert type="success" :message="session('success')" />
        @endif
        @if (session('warning'))
            <x-alert type="warning" :message="session('warning')" />
        @endif

        <section class="client-quote-statistics" aria-label="Résumé de mes devis">
            <a class="client-quote-stat {{ $activeBucket === 'review' ? 'is-active' : '' }}"
               href="{{ route('client.quotes.index', ['bucket' => 'review']) }}" @if ($activeBucket === 'review') aria-current="page" @endif>
                <span class="client-quote-stat__icon client-quote-stat__icon--review"><i class="fa-solid fa-eye" aria-hidden="true"></i></span>
                <span class="client-quote-stat__label">À examiner</span>
                <strong>{{ $statistics['review'] }}</strong>
                <small>Une proposition du professionnel attend votre décision</small>
            </a>
            <a class="client-quote-stat {{ $activeBucket === 'negotiating' ? 'is-active' : '' }}"
               href="{{ route('client.quotes.index', ['bucket' => 'negotiating']) }}" @if ($activeBucket === 'negotiating') aria-current="page" @endif>
                <span class="client-quote-stat__icon client-quote-stat__icon--negotiating"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
                <span class="client-quote-stat__label">En négociation</span>
                <strong>{{ $statistics['negotiating'] }}</strong>
                <small>Propositions successives à suivre</small>
            </a>
            <a class="client-quote-stat {{ $activeBucket === 'accepted' ? 'is-active' : '' }}"
               href="{{ route('client.quotes.index', ['bucket' => 'accepted']) }}" @if ($activeBucket === 'accepted') aria-current="page" @endif>
                <span class="client-quote-stat__icon client-quote-stat__icon--accepted"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                <span class="client-quote-stat__label">Acceptés</span>
                <strong>{{ $statistics['accepted'] }}</strong>
                <small>Retrouvez la commande associée</small>
            </a>
            <a class="client-quote-stat {{ $activeBucket === 'history' ? 'is-active' : '' }}"
               href="{{ route('client.quotes.index', ['bucket' => 'history']) }}" @if ($activeBucket === 'history') aria-current="page" @endif>
                <span class="client-quote-stat__icon client-quote-stat__icon--history"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
                <span class="client-quote-stat__label">Historique</span>
                <strong>{{ $statistics['history'] }}</strong>
                <small>Devis refusés, expirés ou retirés</small>
            </a>
        </section>

        <section class="client-panel client-quote-filters" aria-labelledby="quote-filters-title">
            <div class="client-panel__header">
                <div>
                    <h2 id="quote-filters-title">Rechercher un devis</h2>
                    <p>Recherchez par référence, demande, service ou nom public du professionnel.</p>
                </div>
                @if ($hasFilters)
                    <a class="client-filter-clear" href="{{ route('client.quotes.index') }}">Effacer les filtres</a>
                @endif
            </div>

            <form method="GET" action="{{ route('client.quotes.index') }}" class="client-quote-filter-form">
                <div class="client-field client-quote-search">
                    <label for="quote-search">Recherche</label>
                    <input id="quote-search" name="q" type="search" maxlength="120"
                           value="{{ $filters['q'] ?? '' }}" placeholder="Ex. DEVIS-000123, création de site…">
                </div>

                <div class="client-field">
                    <label for="quote-status">Statut</label>
                    <select id="quote-status" name="status">
                        <option value="">Tous les statuts</option>
                        @foreach ($activeStatuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="client-field">
                    <label for="quote-validity">Validité</label>
                    <select id="quote-validity" name="validity">
                        <option value="">Toutes les échéances</option>
                        <option value="valid" @selected(($filters['validity'] ?? '') === 'valid')>Encore valable</option>
                        <option value="expiring" @selected(($filters['validity'] ?? '') === 'expiring')>Expire dans les 3 jours</option>
                        <option value="expired" @selected(($filters['validity'] ?? '') === 'expired')>Expiré</option>
                    </select>
                </div>

                <div class="client-field">
                    <label for="quote-currency">Devise</label>
                    <select id="quote-currency" name="currency">
                        <option value="">Toutes les devises</option>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="client-field">
                    <label for="quote-received-from">Reçu à partir du</label>
                    <input id="quote-received-from" name="received_from" type="date" value="{{ $filters['received_from'] ?? '' }}">
                </div>

                <div class="client-field">
                    <label for="quote-received-to">Reçu jusqu'au</label>
                    <input id="quote-received-to" name="received_to" type="date" value="{{ $filters['received_to'] ?? '' }}">
                </div>

                <div class="client-field">
                    <label for="quote-sort">Trier par</label>
                    <select id="quote-sort" name="sort">
                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Plus récents</option>
                        <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Plus anciens</option>
                        <option value="deadline" @selected(($filters['sort'] ?? '') === 'deadline')>Date limite</option>
                        <option value="amount_asc" @selected(($filters['sort'] ?? '') === 'amount_asc')>Montant croissant (par devise)</option>
                        <option value="amount_desc" @selected(($filters['sort'] ?? '') === 'amount_desc')>Montant décroissant (par devise)</option>
                    </select>
                </div>

                @if ($activeBucket !== '')
                    <input type="hidden" name="bucket" value="{{ $activeBucket }}">
                @endif

                <div class="client-quote-filter-actions">
                    <p><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Les montants sont regroupés par devise ; USD et CDF ne sont jamais comparés directement.</p>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Appliquer
                    </button>
                </div>
            </form>
        </section>

        @php
            $quoteFilterLabels = ['q' => 'Recherche', 'status' => 'Statut', 'validity' => 'Validité', 'currency' => 'Devise', 'received_from' => 'Reçu à partir du', 'received_to' => 'Reçu jusqu’au', 'sort' => 'Tri'];
            $quoteFilterValues = ['valid' => 'Encore valable', 'expiring' => 'Expire dans les 3 jours', 'expired' => 'Expiré', 'newest' => 'Plus récents', 'oldest' => 'Plus anciens', 'deadline' => 'Date limite', 'amount_asc' => 'Montant croissant (par devise)', 'amount_desc' => 'Montant décroissant (par devise)'];
        @endphp
        @if ($hasFilters)
            <nav class="client-quote-active-filters" aria-label="Critères de recherche actifs">
                <span class="client-quote-active-filters__label">Filtres actifs</span>
                @if ($activeBucket !== '')
                    <a class="client-quote-filter-chip" href="{{ route('client.quotes.index', collect($filters)->except(['bucket'])->all()) }}">
                        {{ match ($activeBucket) { 'review' => 'À examiner', 'negotiating' => 'En négociation', 'accepted' => 'Acceptés', 'history' => 'Historique', default => 'Catégorie' } }}
                        <span aria-hidden="true">×</span><span class="sr-only">Retirer ce filtre</span>
                    </a>
                @endif
                @foreach ($filters as $key => $value)
                    @if ($key !== 'bucket' && filled($value) && ! ($key === 'sort' && $value === 'newest'))
                        @php
                            $displayFilterValue = $key === 'status'
                                ? (\App\Enums\QuotationStatus::tryFrom((string) $value)?->label() ?? 'Statut sélectionné')
                                : ($quoteFilterValues[$value] ?? $value);
                        @endphp
                        <a class="client-quote-filter-chip" href="{{ route('client.quotes.index', collect($filters)->except([$key])->all()) }}">
                            {{ $quoteFilterLabels[$key] ?? 'Filtre' }} : {{ $displayFilterValue }}
                            <span aria-hidden="true">×</span><span class="sr-only">Retirer ce filtre</span>
                        </a>
                    @endif
                @endforeach
                <a class="client-filter-clear" href="{{ route('client.quotes.index') }}">Tout réinitialiser</a>
            </nav>
        @endif

        <div class="client-quote-results-heading">
            <div>
                <h2>
                    @switch($activeBucket)
                        @case('review') À examiner @break
                        @case('negotiating') En négociation @break
                        @case('accepted') Devis acceptés @break
                        @case('history') Historique des décisions @break
                        @default Tous mes devis
                    @endswitch
                </h2>
                <p>{{ $quotes->total() }} résultat(s) · page {{ $quotes->currentPage() }} sur {{ max(1, $quotes->lastPage()) }}</p>
            </div>
        </div>

        @if ($quotes->isEmpty())
            @if ($hasFilters || $activeBucket !== '')
                <x-empty-state icon="fa-filter-circle-xmark" title="Aucun devis ne correspond à ces critères"
                               message="Modifiez la recherche ou retirez certains filtres pour retrouver vos propositions." />
                <div class="client-quote-empty-action">
                    <a class="btn btn-secondary" href="{{ route('client.quotes.index') }}">Afficher tous mes devis</a>
                </div>
            @else
                <x-empty-state icon="fa-file-invoice-dollar" title="Aucun devis pour le moment"
                               message="Les propositions envoyées par les professionnels après vos demandes apparaîtront ici." />
                <div class="client-quote-empty-action">
                    <a class="btn btn-primary" href="{{ route('client.requests.index') }}">Consulter mes demandes</a>
                </div>
            @endif
        @else
            <div class="client-quote-list">
                @foreach ($quotes as $quote)
                    @php
                        $offer = $quote->currentOffer;
                        $professional = $quote->serviceRequest?->professional;
                        $isAwaitingClient = $offer
                            && $offer->actor_type === \App\Enums\QuotationOfferActor::PROFESSIONAL
                            && in_array($quote->status, [\App\Enums\QuotationStatus::SENT, \App\Enums\QuotationStatus::NEGOTIATING], true)
                            && $offer->valid_until?->isFuture();
                        $isExpiringSoon = $isAwaitingClient && $offer->valid_until->lte(now()->addDays(3));
                    @endphp
                    <article class="client-quote-card {{ $isAwaitingClient ? 'client-quote-card--needs-action' : '' }}">
                        <div class="client-quote-card__top">
                            <div class="client-quote-card__identity">
                                <span class="client-quote-card__icon"><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i></span>
                                <div>
                                    <p class="client-module__eyebrow">DEVIS-{{ str_pad((string) $quote->id, 6, '0', STR_PAD_LEFT) }}</p>
                                    <h3>{{ $quote->serviceRequest?->service?->title ?? $quote->serviceRequest?->title ?? 'Prestation de service' }}</h3>
                                    @if ($quote->serviceRequest?->title && $quote->serviceRequest?->title !== $quote->serviceRequest?->service?->title)
                                        <p class="client-quote-card__request">Demande : {{ $quote->serviceRequest->title }}</p>
                                    @endif
                                    <p class="client-quote-card__professional">
                                        <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                        {{ $professional?->display_name ?: $professional?->user?->name ?: 'Professionnel PROXIWORK' }}
                                    </p>
                                </div>
                            </div>
                            <div class="client-quote-card__status">
                                <span class="status-badge status-badge--{{ $quote->status->value }}">{{ $quote->status->label() }}</span>
                                @if ($isAwaitingClient)
                                    <span class="client-quote-action-label"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Votre décision</span>
                                @endif
                            </div>
                        </div>

                        <div class="client-quote-card__body">
                            <div class="client-quote-card__amount">
                                <span>Montant proposé</span>
                                @if ($offer)
                                    <strong>{{ number_format((float) $offer->amount, 2, ',', ' ') }} <span>{{ $offer->currency }}</span></strong>
                                    <small>Proposition v{{ $offer->version }}</small>
                                @else
                                    <strong>Montant indisponible</strong>
                                @endif
                            </div>
                            <dl class="client-quote-card__meta">
                                <div>
                                    <dt>Reçu le</dt>
                                    <dd>{{ optional($quote->created_at)->format('d/m/Y à H:i') }}</dd>
                                </div>
                                <div>
                                    <dt>Valable jusqu'au</dt>
                                    <dd>{{ optional($offer?->valid_until)->format('d/m/Y à H:i') ?? 'Non défini' }}</dd>
                                </div>
                                <div>
                                    <dt>Délai proposé</dt>
                                    <dd>{{ $offer ? $offer->duration_value.' '.$offer->duration_unit->value : 'Non défini' }}</dd>
                                </div>
                            </dl>
                        </div>

                        @if ($isExpiringSoon)
                            <p class="client-quote-expiry-note"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Décision attendue avant le {{ $offer->valid_until->format('d/m/Y à H:i') }}.</p>
                        @elseif ($offer && ! $offer->valid_until?->isFuture() && in_array($quote->status, [\App\Enums\QuotationStatus::SENT, \App\Enums\QuotationStatus::NEGOTIATING], true))
                            <p class="client-quote-expiry-note client-quote-expiry-note--expired"><i class="fa-solid fa-clock" aria-hidden="true"></i> La date limite est dépassée ; l'acceptation est bloquée.</p>
                        @elseif ($offer?->actor_type === \App\Enums\QuotationOfferActor::CLIENT && $quote->status === \App\Enums\QuotationStatus::NEGOTIATING)
                            <p class="client-quote-waiting-note"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Votre contre-proposition attend la réponse du professionnel.</p>
                        @endif

                        <div class="client-quote-card__footer">
                            <a class="btn btn-secondary btn-sm" href="{{ route('client.requests.show', $quote->serviceRequest) }}">
                                <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Demande liée
                            </a>
                            @if ($quote->status === \App\Enums\QuotationStatus::ACCEPTED && $quote->order)
                                <a class="btn btn-secondary btn-sm" href="{{ route('client.orders.show', $quote->order) }}">
                                    <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Commande {{ $quote->order->order_number }}
                                </a>
                            @endif
                            <a class="btn btn-primary btn-sm" href="{{ route('client.quotes.show', $quote) }}">
                                {{ $isAwaitingClient ? 'Répondre au devis' : 'Voir le devis' }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="client-pagination">{{ $quotes->links() }}</div>
        @endif
    </div>
@endsection
