@extends('layouts.admin')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/finance.css', 'resources/js/pages/admin/finance.js'])
    @endunless
@endpush

@section('page_eyebrow', 'SUPERVISION FINANCIÈRE')
@section('page_title', 'Vue générale des finances')
@section('page_description', 'Suivez les paiements confirmés, les commissions et les soldes professionnels sans mélanger les devises.')
@section('page_actions')
    <form method="GET" action="{{ route('admin.finance.overview') }}" class="admin-finance-period">
        <label for="finance-from">Du</label>
        <input id="finance-from" type="date" name="from" value="{{ ($filters['from'] ?? $overview['period']['from']->toDateString()) }}">
        <label for="finance-to">Au</label>
        <input id="finance-to" type="date" name="to" value="{{ ($filters['to'] ?? $overview['period']['to']->toDateString()) }}">
        <button class="button button--primary button--small" type="submit">Actualiser</button>
    </form>
@endsection

@section('content')
<div class="admin-finance" data-finance-charts="{{ json_encode($overview['charts'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
    <div class="admin-finance__meta">
        <span>Période : {{ $overview['period']['from']->format('d/m/Y') }} – {{ $overview['period']['to']->format('d/m/Y') }}</span>
        <span>Données actualisées à {{ $overview['updated_at']->format('d/m/Y H:i') }}</span>
    </div>

    <section class="admin-finance__section">
        <div class="admin-finance__section-heading"><div><h2>Activité des paiements</h2><p>Les montants ci-dessous ne comprennent que les paiements confirmés et datés.</p></div><a href="{{ route('admin.payments.index') }}">Consulter les paiements</a></div>
        <div class="admin-finance__kpis">
            <article class="admin-finance__kpi"><span>Paiements initiés</span><strong>{{ number_format($overview['payment_statuses']['initiated'] ?? 0) }}</strong></article>
            <article class="admin-finance__kpi"><span>Paiements en attente</span><strong>{{ number_format(($overview['payment_statuses']['pending'] ?? 0) + ($overview['payment_statuses']['processing'] ?? 0)) }}</strong></article>
            <article class="admin-finance__kpi"><span>Paiements confirmés</span><strong>{{ number_format($overview['payment_statuses']['succeeded'] ?? 0) }}</strong></article>
            <article class="admin-finance__kpi"><span>Paiements échoués</span><strong>{{ number_format($overview['payment_statuses']['failed'] ?? 0) }}</strong></article>
        </div>
        <div class="admin-finance__currency-grid">
            @forelse($overview['confirmed_payments'] as $currency => $amount)
                <article class="admin-finance__currency-card"><span>Volume confirmé · {{ $currency }}</span><strong>{{ number_format((float) $amount, 2, ',', ' ') }} {{ $currency }}</strong></article>
            @empty
                <p class="admin-finance__empty">Aucun paiement confirmé sur cette période.</p>
            @endforelse
        </div>
    </section>

    <section class="admin-finance__section">
        <div class="admin-finance__section-heading"><div><h2>Revenus de la plateforme</h2><p>Les commissions sont présentées séparément des sommes revenant aux professionnels.</p></div><a href="{{ route('admin.finance.commissions') }}">Voir les commissions</a></div>
        <div class="admin-finance__currency-grid">
            @forelse($overview['commissions'] as $currency => $totals)
                <article class="admin-finance__currency-card">
                    <span>Commission comptabilisée · {{ $currency }}</span>
                    <strong>{{ number_format((float) $totals['commission'], 2, ',', ' ') }} {{ $currency }}</strong>
                    <small>{{ number_format((float) $totals['professional_net'], 2, ',', ' ') }} {{ $currency }} de revenu net professionnel · {{ number_format($totals['count']) }} commissions</small>
                </article>
            @empty
                <p class="admin-finance__empty">Aucune commission comptabilisée sur cette période.</p>
            @endforelse
        </div>
    </section>

    <section class="admin-finance__section">
        <div class="admin-finance__section-heading"><div><h2>Évolution financière</h2><p>Volumes journaliers par devise, sans additionner des monnaies différentes.</p></div></div>
        @if($overview['charts']['is_limited'])
            <p class="admin-finance__muted">Le graphique présente les 90 derniers jours de la période sélectionnée. Les indicateurs de synthèse couvrent toute la période.</p>
        @endif
        <div class="admin-finance__chart-grid">
            @forelse($overview['charts']['currencies'] as $currency => $series)
                <article class="admin-finance__chart-card">
                    <h3>Flux confirmés · {{ $currency }}</h3>
                    <div class="admin-finance__chart"><canvas data-finance-chart="{{ $currency }}" role="img" aria-label="Évolution des paiements confirmés et commissions en {{ $currency }}"></canvas></div>
                </article>
            @empty
                <p class="admin-finance__empty">Aucune donnée de paiement ou de commission à représenter sur cette période.</p>
            @endforelse
        </div>
    </section>

    <section class="admin-finance__section">
        <div class="admin-finance__section-heading"><div><h2>Situation des portefeuilles professionnels</h2><p>Soldes actuels, indépendamment de la période sélectionnée.</p></div><a href="{{ route('admin.finance.wallets') }}">Superviser les portefeuilles</a></div>
        <div class="admin-finance__currency-grid">
            @forelse($overview['wallets'] as $currency => $totals)
                <article class="admin-finance__currency-card">
                    <span>{{ number_format($totals['count']) }} portefeuilles · {{ $currency }}</span>
                    <strong>{{ number_format((float) $totals['available'], 2, ',', ' ') }} {{ $currency }}</strong>
                    <small>Disponible</small>
                    <div class="admin-finance__balance-line"><span>En attente</span><b>{{ number_format((float) $totals['pending'], 2, ',', ' ') }} {{ $currency }}</b></div>
                    <div class="admin-finance__balance-line"><span>Bloqué</span><b>{{ number_format((float) $totals['locked'], 2, ',', ' ') }} {{ $currency }}</b></div>
                </article>
            @empty
                <p class="admin-finance__empty">Aucun portefeuille enregistré.</p>
            @endforelse
        </div>
    </section>

    <section class="admin-finance__section">
        <div class="admin-finance__section-heading"><div><h2>Alertes à examiner</h2><p>Indicateurs techniques nécessitant une vérification, sans correction automatique.</p></div></div>
        <div class="admin-finance__alerts">
            <a href="{{ route('admin.finance.transactions', ['status' => 'unknown']) }}"><span><strong>Transactions au résultat incertain</strong><small>Une confirmation fiable n'a pas été obtenue.</small></span><b>{{ number_format($overview['alerts']['unknown_transactions']) }}</b></a>
            <a href="{{ route('admin.payments.index', ['status' => 'succeeded']) }}"><span><strong>Paiements confirmés sans commission associée</strong><small>À examiner avant toute intervention sur les soldes.</small></span><b>{{ number_format($overview['alerts']['payments_missing_commission']) }}</b></a>
        </div>
    </section>

    <section class="admin-finance__section">
        <div class="admin-finance__section-heading"><div><h2>Transactions récentes</h2><p>Aperçu des dernières transactions de paiement de la période.</p></div><a href="{{ route('admin.finance.transactions') }}">Toutes les transactions</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Référence</th><th>Commande</th><th>Client</th><th>Professionnel</th><th>Montant</th><th>Statut</th><th>Date</th></tr></thead><tbody>
            @forelse($overview['recent_transactions'] as $transaction)
                <tr>
                    <td><a href="{{ route('admin.finance.transactions.show', $transaction) }}">TX-{{ $transaction->id }}</a></td>
                    <td>{{ $transaction->payment?->order?->order_number ?? '—' }}</td>
                    <td>{{ $transaction->payment?->client?->name ?? '—' }}</td>
                    <td>{{ $transaction->payment?->order?->professional?->user?->name ?? '—' }}</td>
                    <td>{{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</td>
                    <td><span class="admin-status admin-status--{{ $transaction->status->value }}">{{ $transaction->status->value }}</span></td>
                    <td>{{ $transaction->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="admin-empty">Aucune transaction sur cette période.</div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
