@extends('layouts.professional')

@section('title', 'Mes revenus')

@section('content')
    @push('head')
        @vite(['resources/css/pages/professional/revenues.css', 'resources/js/pages/professional/revenues.js'])
    @endpush

    <div class="revenue-page">
        <header class="revenue-page__header">
            <div>
                <p class="revenue-kicker">FINANCES · TABLEAU DE PILOTAGE</p>
                <h1>Mes revenus</h1>
                <p class="revenue-muted">Consultez les revenus issus des commissions comptabilisées, les paiements confirmés admissibles, les prestations terminées et les fonds encore en attente de libération.</p>
            </div>
            <a class="revenue-button revenue-button--secondary" href="{{ route('professional.wallet') }}">Mon portefeuille</a>
        </header>

        <section class="revenue-card" aria-labelledby="revenue-filters-title">
            <div class="revenue-card__header">
                <div>
                    <h2 id="revenue-filters-title">Période et filtres</h2>
                    <p class="revenue-muted">Période analysée : {{ $period['label'] }}. Les dates d’analyse dépendent de l’événement : comptabilisation, paiement ou fin de prestation.</p>
                </div>
            </div>
            <form class="revenue-filter-form" method="GET" action="{{ route('professional.revenues.index') }}">
                <label for="period">Période
                    <select id="period" name="period">
                        <option value="7d" @selected($filters['period'] === '7d')>7 derniers jours</option>
                        <option value="30d" @selected($filters['period'] === '30d')>30 derniers jours</option>
                        <option value="this_month" @selected($filters['period'] === 'this_month')>Ce mois</option>
                        <option value="this_quarter" @selected($filters['period'] === 'this_quarter')>Ce trimestre</option>
                        <option value="this_year" @selected($filters['period'] === 'this_year')>Cette année</option>
                        <option value="custom" @selected($filters['period'] === 'custom')>Période personnalisée</option>
                    </select>
                </label>
                <label for="from">Du
                    <input id="from" type="date" name="from" value="{{ $filters['period'] === 'custom' ? $period['from']->format('Y-m-d') : '' }}">
                </label>
                <label for="to">Au
                    <input id="to" type="date" name="to" value="{{ $filters['period'] === 'custom' ? $period['to']->format('Y-m-d') : '' }}">
                </label>
                <label for="currency">Devise
                    <select id="currency" name="currency">
                        <option value="">Toutes les devises (séparément)</option>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency }}" @selected(($filters['currency'] ?? null) === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="service_id">Service
                    <select id="service_id" name="service_id">
                        <option value="">Tous les services</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $service->id)>{{ $service->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="order_id">Commande (ID)
                    <input id="order_id" type="number" min="1" name="order_id" value="{{ $filters['order_id'] ?? '' }}" placeholder="Ex. 125">
                </label>
                <label for="reference">Référence d’opération
                    <input id="reference" type="text" name="reference" maxlength="64" value="{{ $filters['reference'] ?? '' }}" placeholder="COM-123 ou référence commande">
                </label>
                <label for="status">État de l’historique
                    <select id="status" name="status">
                        <option value="all" @selected($filters['status'] === 'all')>Tous les états</option>
                        <option value="posted" @selected($filters['status'] === 'posted')>Comptabilisées</option>
                        <option value="reversed" @selected($filters['status'] === 'reversed')>Annulées / corrigées</option>
                    </select>
                </label>
                <div class="revenue-filter-form__actions">
                    <button class="revenue-button revenue-button--primary" type="submit">Appliquer</button>
                    <a class="revenue-button revenue-button--secondary" href="{{ route('professional.revenues.index') }}">Réinitialiser</a>
                    <a class="revenue-button revenue-button--secondary" href="{{ route('professional.revenues.export', request()->except('page')) }}">Exporter CSV</a>
                </div>
            </form>
            <p class="revenue-filter-note">Les indicateurs distinguent les dates de comptabilisation, de confirmation du paiement et de fin de prestation. Le filtre d’état et la recherche par référence agissent sur l’historique et l’export. Les indicateurs synthétiques restent agrégés selon la période, la devise, le service et la commande.</p>
        </section>

        @if ($currencies->isNotEmpty())
            @foreach ($currencies as $currency)
                @php
                    $summaryRow = $summary->firstWhere('currency', $currency);
                    $completedRow = $completedOrders->firstWhere('currency', $currency);
                    $paymentRow = $confirmedPayments->firstWhere('currency', $currency);
                    $pendingRow = $pendingBalances->firstWhere('currency', $currency);
                    $series = collect($evolution)->firstWhere('currency', $currency);
                    $servicesForCurrency = $serviceBreakdown->where('currency', $currency)->take(10)->values();
                    $serviceChartPayload = [
                        'labels' => $servicesForCurrency->pluck('service_title')->all(),
                        'values' => $servicesForCurrency->map(fn ($row) => (float) $row->net_amount)->all(),
                        'currency' => $currency,
                        'label' => 'Revenus nets par service',
                    ];
                @endphp
                <section class="revenue-currency-section" aria-label="Synthèse en {{ $currency }}">
                    <h2>{{ $currency }} · Synthèse de la période</h2>
                    <div class="revenue-metrics">
                        <article class="revenue-metric">
                            <span class="revenue-metric__label">Revenus nets comptabilisés</span>
                            <strong>{{ number_format((float) ($summaryRow->net_amount ?? 0), 2, ',', ' ') }} {{ $currency }}</strong>
                            <small>Commissions enregistrées et non annulées sur la période.</small>
                        </article>
                        <article class="revenue-metric">
                            <span class="revenue-metric__label">Revenus bruts attribués</span>
                            <strong>{{ number_format((float) ($summaryRow->gross_amount ?? 0), 2, ',', ' ') }} {{ $currency }}</strong>
                            <small>Montants des paiements associés aux commissions comptabilisées, avant frais de plateforme.</small>
                        </article>
                        <article class="revenue-metric">
                            <span class="revenue-metric__label">Paiements confirmés</span>
                            <strong>{{ number_format((float) ($paymentRow->paid_amount ?? 0), 2, ',', ' ') }} {{ $currency }}</strong>
                            <small>{{ $paymentRow->payments_count ?? 0 }} paiement(s) confirmé(s) rattaché(s) à une commission comptabilisée.</small>
                        </article>
                        <article class="revenue-metric">
                            <span class="revenue-metric__label">Frais de plateforme</span>
                            <strong>{{ number_format((float) ($summaryRow->commission_amount ?? 0), 2, ',', ' ') }} {{ $currency }}</strong>
                            <small>Frais enregistrés avec les commissions admissibles.</small>
                        </article>
                        <article class="revenue-metric">
                            <span class="revenue-metric__label">Prestations terminées</span>
                            <strong>{{ number_format((float) ($completedRow->total_amount ?? 0), 2, ',', ' ') }} {{ $currency }}</strong>
                            <small>{{ $completedRow->orders_count ?? 0 }} commande(s) terminée(s). Valeur des commandes, pas un revenu net.</small>
                        </article>
                    </div>

                    <div class="revenue-card">
                        <div class="revenue-card__header">
                            <div>
                                <h2>Évolution des revenus nets</h2>
                                <p class="revenue-muted">Série par {{ ($series['granularity'] ?? 'day') === 'day' ? 'jour' : ((($series['granularity'] ?? '') === 'week') ? 'semaine' : 'mois') }}. Les périodes sans activité sont représentées par zéro.</p>
                            </div>
                        </div>
                        <div class="revenue-chart" data-chart-container>
                            <canvas aria-label="Évolution des revenus nets en {{ $currency }}" role="img" data-revenue-chart data-chart-type="line" data-chart-payload="{{ json_encode(['labels' => $series['labels'] ?? [], 'values' => $series['values'] ?? [], 'currency' => $currency, 'label' => 'Revenus nets'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"></canvas>
                        </div>
                    </div>

                    <div class="revenue-card">
                        <div class="revenue-card__header">
                            <div>
                                <h2>Revenus nets par service</h2>
                                <p class="revenue-muted">Les barres utilisent les commissions comptabilisées. Un revenu élevé n’est pas une mesure du bénéfice après les coûts du professionnel.</p>
                            </div>
                        </div>
                        @if ($servicesForCurrency->isNotEmpty())
                            <div class="revenue-chart" data-chart-container>
                                <canvas aria-label="Revenus nets par service en {{ $currency }}" role="img" data-revenue-chart data-chart-type="bar" data-chart-payload="{{ json_encode($serviceChartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"></canvas>
                            </div>
                            <div class="revenue-table-wrap">
                                <table class="revenue-table">
                                    <thead><tr><th>Service</th><th>Commandes</th><th>Revenus nets</th></tr></thead>
                                    <tbody>
                                        @foreach ($servicesForCurrency as $serviceRevenue)
                                            <tr><td>@if ($serviceRevenue->service_id)<a href="{{ request()->fullUrlWithQuery(['service_id' => $serviceRevenue->service_id]) }}">{{ $serviceRevenue->service_title }}</a>@else{{ $serviceRevenue->service_title }}@endif</td><td>{{ $serviceRevenue->orders_count }}</td><td><strong>{{ number_format((float) $serviceRevenue->net_amount, 2, ',', ' ') }} {{ $currency }}</strong></td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="revenue-empty">Aucun revenu par service n’a été comptabilisé sur cette période.</p>
                        @endif
                    </div>

                    <div class="revenue-metric">
                        <span class="revenue-metric__label">Fonds en attente de libération · solde actuel</span>
                        <strong>{{ number_format((float) ($pendingRow->pending_balance ?? 0), 2, ',', ' ') }} {{ $currency }}</strong>
                        <small>Ce solde vient du portefeuille et n’est limité ni à la période, ni au service, ni à la commande sélectionnés. Il ne s’ajoute pas aux revenus nets.</small>
                    </div>
                </section>
            @endforeach
        @else
            <div class="revenue-empty">
                <h2>Aucune donnée financière pour le moment</h2>
                <p>Les indicateurs apparaîtront lorsqu’une commission sera comptabilisée ou qu’un portefeuille sera créé. Aucun montant n’est estimé à partir du prix affiché dans les services.</p>
            </div>
        @endif

        <section class="revenue-card">
            <div class="revenue-card__header">
                <div>
                    <h2>Historique des commissions</h2>
                    <p class="revenue-muted">Chaque ligne conserve la référence de l’opération d’origine. Les montants sont calculés côté serveur et les détails sont limités au professionnel connecté.</p>
                </div>
            </div>

            @if ($commissions->isEmpty())
                <div class="revenue-empty">
                    <h3>Aucune opération ne correspond aux filtres</h3>
                    <p>Essayez une autre période ou réinitialisez les filtres. Les demandes de remboursement ne sont pas des remboursements exécutés et ne sont pas présentées comme des mouvements comptabilisés.</p>
                </div>
            @else
                <div class="revenue-table-wrap">
                    <table class="revenue-table">
                        <thead><tr><th>Date comptabilisée</th><th>Référence</th><th>Commande / service</th><th>Brut</th><th>Frais</th><th>Net</th><th>État</th><th>Détail</th></tr></thead>
                        <tbody>
                            @foreach ($commissions as $commission)
                                <tr>
                                    <td>{{ $commission->posted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>COM-{{ $commission->id }}</td>
                                    <td>
                                        <a href="{{ route('professional.orders.show', $commission->order_id) }}">{{ $commission->order?->order_number ?? '#'.$commission->order_id }}</a>
                                        <small>{{ $commission->order?->items->first()?->service_title ?? 'Service indisponible' }}</small>
                                    </td>
                                    <td>{{ number_format((float) $commission->gross_amount, 2, ',', ' ') }} {{ $commission->currency }}</td>
                                    <td>{{ number_format((float) $commission->commission_amount, 2, ',', ' ') }} {{ $commission->currency }}</td>
                                    <td><strong>{{ number_format((float) $commission->net_amount, 2, ',', ' ') }} {{ $commission->currency }}</strong></td>
                                    <td><span class="revenue-status {{ $commission->status->value === 'reversed' ? 'revenue-status--reversed' : '' }}">{{ $commission->status->value === 'posted' ? 'Comptabilisée' : 'Annulée' }}</span></td>
                                    <td><a class="revenue-link" href="{{ route('professional.revenues.show', ['commission' => $commission->id] + request()->query()) }}">Justificatif</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="revenue-pagination">{{ $commissions->links() }}</div>
            @endif
        </section>

        <section class="revenue-card">
            <h2>À propos des remboursements et corrections</h2>
            <p class="revenue-muted">PROXIWORK conserve les demandes de remboursement séparément des commissions. Tant qu’un remboursement confirmé n’est pas relié à une écriture de correction financière, il ne doit pas être soustrait arbitrairement des revenus nets. Cette limite du modèle actuel est signalée ici pour éviter de présenter un total incomplet comme un relevé financier définitif.</p>
            <nav class="revenue-related-links" aria-label="Pages financières">
                <a href="{{ route('professional.wallet') }}">Portefeuille</a>
                <a href="{{ route('professional.withdrawals') }}">Retraits</a>
            </nav>
        </section>

        <p class="revenue-filter-note">Dernière mise à jour des commissions : {{ $lastUpdatedAt ? \Illuminate\Support\Carbon::parse($lastUpdatedAt)->format('d/m/Y H:i') : 'aucune commission enregistrée' }}. Les graphiques sont des outils de consultation, pas un relevé comptable certifié.</p>
    </div>
@endsection
