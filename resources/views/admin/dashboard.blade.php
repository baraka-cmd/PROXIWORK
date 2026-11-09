@extends('layouts.admin')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/dashboard.css', 'resources/js/pages/admin/dashboard.js'])
    @endunless
@endpush

@section('page_eyebrow', 'PILOTAGE DE LA PLATEFORME')
@section('page_title', 'Tableau de bord')
@section('page_description', 'Vue de pilotage de PROXIWORK : activité, opérations à examiner et tendances de la période.')
@section('page_actions')
    <form class="admin-dashboard-period" method="GET" action="{{ route('admin.dashboard') }}">
        <label for="dashboard-period">Période</label>
        <select id="dashboard-period" name="period" data-period-select>
            @foreach(['today' => 'Aujourd’hui', 'yesterday' => 'Hier', '7d' => '7 derniers jours', '30d' => '30 derniers jours', '90d' => '90 derniers jours', 'this_month' => 'Ce mois', 'last_month' => 'Mois précédent', 'this_year' => 'Cette année', 'custom' => 'Personnalisée'] as $key => $label)
                <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="admin-dashboard-period__custom" data-custom-range @if($periodKey !== 'custom') hidden @endif>
            <label class="sr-only" for="dashboard-from">Du</label><input id="dashboard-from" type="date" name="from" value="{{ $from->toDateString() }}">
            <span aria-hidden="true">→</span>
            <label class="sr-only" for="dashboard-to">Au</label><input id="dashboard-to" type="date" name="to" value="{{ $to->toDateString() }}">
        </div>
        <button class="button button--primary button--small" type="submit">Actualiser</button>
    </form>
@endsection

@section('content')
@php
    $range = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $ordersUrl = fn (string $status) => route('admin.orders.index', ['status' => $status]);
    $requestUrl = fn (?string $status = null) => route('admin.service-requests.index', array_filter([
        'status' => $status,
        'requested_from' => $from->toDateString(),
        'requested_to' => $to->toDateString(),
    ], fn ($value) => $value !== null));
    $completedOrdersUrl = route('admin.orders.index', [
        'status' => 'completed',
        'completed_from' => $from->toDateString(),
        'completed_to' => $to->toDateString(),
    ]);
    $usersCanView = auth()->user()->hasPermissionTo('admin.users.view');
    $prosCanView = auth()->user()->hasPermissionTo('admin.professionals.view');
    $ordersCanView = auth()->user()->hasPermissionTo('orders.view');
    $paymentsCanView = auth()->user()->hasPermissionTo('payments.view');
    $reportsCanView = auth()->user()->hasPermissionTo('reports.manage');
    $supportCanView = auth()->user()->hasPermissionTo('support.manage');
    $withdrawalsCanView = auth()->user()->hasPermissionTo('admin.withdrawals.manage');
    $servicesCanView = auth()->user()->hasPermissionTo('admin.services.view');
    $requestsCanView = auth()->user()->hasPermissionTo('requests.view');
    $categoriesCanView = auth()->user()->hasPermissionTo('categories.view');
    $catalogAnalyticsCanView = $servicesCanView && $requestsCanView;
    $communityChartTotal = ($usersCanView ? $dashboard['clients']['total'] : 0) + ($prosCanView ? $dashboard['professionals']['total'] : 0);
    $changeLabel = fn (?float $change) => $change === null
        ? 'Nouvelle activité (aucune base précédente)'
        : (($change > 0 ? '+' : '').number_format($change, 1, ',', ' ').'% vs période précédente');
@endphp
<div class="admin-dashboard" data-admin-dashboard
    data-charts="{{ json_encode([
        'labels' => $dashboard['charts']['labels'],
        'registrations' => ['clients' => $usersCanView ? $dashboard['charts']['registrations']['clients'] : [], 'professionals' => $prosCanView ? $dashboard['charts']['registrations']['professionals'] : []],
        'community' => collect([
            $usersCanView ? ['label' => 'Clients', 'value' => $dashboard['clients']['total'], 'url' => route('admin.users.index', ['role' => 'client'])] : null,
            $prosCanView ? ['label' => 'Professionnels', 'value' => $dashboard['professionals']['total'], 'url' => route('admin.professionals.index')] : null,
        ])->filter()->values(),
        'registrationUrls' => [
            collect($dashboard['charts']['dates'])->map(fn ($date) => $usersCanView ? route('admin.users.index', ['role' => 'client', 'created_from' => $date, 'created_to' => $date]) : null)->values(),
            collect($dashboard['charts']['dates'])->map(fn ($date) => $prosCanView ? route('admin.professionals.index', ['created_from' => $date, 'created_to' => $date]) : null)->values(),
        ],
        'orderStatuses' => collect($ordersCanView ? $dashboard['charts']['order_statuses'] : [])->map(fn ($count, $status) => ['label' => match ($status) { 'pending_payment' => 'Paiement en attente', 'confirmed' => 'Confirmée', 'in_progress' => 'En cours', 'delivery_submitted' => 'Livraison soumise', 'completed' => 'Terminée', 'cancelled' => 'Annulée', 'disputed' => 'En litige', default => str_replace('_', ' ', $status) }, 'value' => $count, 'url' => $ordersUrl($status)])->values(),
        'transactionStatuses' => collect($paymentsCanView ? $dashboard['financial']['transaction_statuses'] : [])->map(fn ($count, $status) => ['label' => match ($status) { 'initiated' => 'Initiée', 'pending' => 'En attente', 'processing' => 'En traitement', 'unknown' => 'À vérifier', 'succeeded' => 'Confirmée', 'failed' => 'Échouée', 'cancelled' => 'Annulée', 'expired' => 'Expirée', 'refunded' => 'Remboursée', default => str_replace('_', ' ', $status) }, 'value' => $count, 'url' => route('admin.payments.index', ['transaction_status' => $status, 'transaction_from' => $from->toDateString(), 'transaction_to' => $to->toDateString()])])->values(),
        'financialTrends' => $paymentsCanView ? collect($dashboard['financial']['trend'])->mapWithKeys(function ($series, $currency) {
            return [$currency => [
                'labels' => $series['labels'],
                'payments' => $series['payments'],
                'commissions' => $series['commissions'],
                'paymentUrls' => collect($series['dates'])->map(fn ($date) => route('admin.payments.index', ['transaction_status' => 'succeeded', 'processed_from' => $date, 'processed_to' => $date]))->values(),
                'commissionUrls' => collect($series['dates'])->map(fn ($date) => route('admin.payments.index', ['commission_status' => 'posted', 'commission_from' => $date, 'commission_to' => $date]))->values(),
            ]];
        })->all() : [],
        'categories' => ($catalogAnalyticsCanView ? $dashboard['catalog']['requests_by_category'] : collect())->map(fn ($row) => ['label' => $row->name, 'value' => (int) $row->requests_count, 'url' => route('admin.service-requests.index', ['category_id' => $row->id, 'requested_from' => $from->toDateString(), 'requested_to' => $to->toDateString()])])->values(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
    <div class="admin-dashboard__meta"><span>Du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span><span>Données actualisées à {{ \Illuminate\Support\Carbon::parse($dashboard['updated_at'])->format('d/m/Y H:i') }}</span></div>

    <section aria-labelledby="admin-platform-kpis" class="admin-dashboard__section">
        <div class="admin-dashboard__section-heading"><div><p class="admin-dashboard__eyebrow">APERÇU</p><h2 id="admin-platform-kpis">La plateforme en un coup d’œil</h2><p>Les comptes sont des cumuls actuels; les inscriptions et opérations sont limitées à la période choisie.</p></div></div>
        <div class="admin-dashboard__kpis">
            @if($usersCanView)<a class="admin-dashboard__kpi" href="{{ route('admin.users.index') }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Utilisateurs inscrits</span><strong>{{ number_format($dashboard['users']['total']) }}</strong><small>{{ number_format($dashboard['users']['new']) }} nouveaux sur la période</small><small>{{ $changeLabel($dashboard['comparisons']['users_new']['change_percent']) }}</small></span></a>@endif
            @if($usersCanView)<a class="admin-dashboard__kpi" href="{{ route('admin.users.index', ['role' => 'client']) }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Clients</span><strong>{{ number_format($dashboard['clients']['total']) }}</strong><small>{{ number_format($dashboard['clients']['new']) }} nouveaux sur la période</small><small>{{ $changeLabel($dashboard['comparisons']['clients_new']['change_percent']) }}</small></span></a>@endif
            @if($prosCanView)<a class="admin-dashboard__kpi" href="{{ route('admin.professionals.index') }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Professionnels</span><strong>{{ number_format($dashboard['professionals']['total']) }}</strong><small>{{ number_format($dashboard['professionals']['new']) }} nouveaux sur la période</small><small>{{ $changeLabel($dashboard['comparisons']['professionals_new']['change_percent']) }}</small></span></a>@endif
            @if($usersCanView)<a class="admin-dashboard__kpi admin-dashboard__kpi--warning" href="{{ route('admin.users.index', ['account_status' => 'suspended']) }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-user-lock" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Comptes suspendus</span><strong>{{ number_format($dashboard['community']['suspended']) }}</strong><small>État actuel des comptes</small></span></a>@endif
        </div>
    </section>


    <section class="admin-dashboard__section" aria-labelledby="admin-commercial-kpis">
        <div class="admin-dashboard__section-heading"><div><p class="admin-dashboard__eyebrow">ACTIVITÉ COMMERCIALE</p><h2 id="admin-commercial-kpis">Demandes et commandes sur la période</h2><p>Les demandes sont comptées hors brouillons; les commandes terminées sont filtrées sur leur date réelle de finalisation.</p></div></div>
        <div class="admin-dashboard__kpis">
            @if(auth()->user()->hasPermissionTo('requests.view'))<a class="admin-dashboard__kpi" href="{{ $requestUrl() }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Demandes publiées</span><strong>{{ number_format($dashboard['funnel']['requests']) }}</strong><small>Créées pendant la période</small><small>{{ $changeLabel($dashboard['comparisons']['requests']['change_percent']) }}</small></span></a>@endif
            @if(auth()->user()->hasPermissionTo('requests.view'))<a class="admin-dashboard__kpi" href="{{ $requestUrl('quoted') }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Demandes au stade devis</span><strong>{{ number_format($dashboard['funnel']['quotations']) }}</strong><small>État actuel des demandes créées sur la période</small></span></a>@endif
            @if($ordersCanView)<a class="admin-dashboard__kpi" href="{{ route('admin.orders.index', $range) }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Commandes créées</span><strong>{{ number_format($dashboard['funnel']['orders']) }}</strong><small>Créées pendant la période</small><small>{{ $changeLabel($dashboard['comparisons']['orders']['change_percent']) }}</small></span></a>
            <a class="admin-dashboard__kpi" href="{{ $completedOrdersUrl }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Commandes terminées</span><strong>{{ number_format($dashboard['funnel']['completed_orders']) }}</strong><small>Finalisées pendant la période</small></span></a><a class="admin-dashboard__kpi" href="{{ route('admin.orders.index', ['status_group' => 'active']) }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-spinner" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Commandes actuellement en cours</span><strong>{{ number_format($dashboard['community']['active_orders']) }}</strong><small>Statuts opérationnels actifs</small></span></a>@endif
        </div>
    </section>

    <section class="admin-dashboard__section" aria-labelledby="admin-attention-title">
        <div class="admin-dashboard__section-heading"><div><p class="admin-dashboard__eyebrow">INTERVENTION</p><h2 id="admin-attention-title">À traiter maintenant</h2><p>Dossiers ouverts selon les statuts réellement utilisés par PROXIWORK.</p></div></div>
        <div class="admin-dashboard__attention">
            @if($prosCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.verification.index', ['verification_status' => 'pending']) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span><span><strong>Vérifications en attente</strong><small>Dossiers nécessitant une décision</small></span><b>{{ number_format($dashboard['attention']['verification']) }}</b></a>@endif
            @if($withdrawalsCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.withdrawals.index', ['status_group' => 'pending_review']) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i></span><span><strong>Retraits à examiner</strong><small>Demandes en attente ou à vérifier</small></span><b>{{ number_format($dashboard['attention']['withdrawals']) }}</b></a>@endif
            @if($reportsCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.reports.index', ['status' => 'pending']) }}"><span class="admin-dashboard__attention-icon is-red"><i class="fa-solid fa-flag" aria-hidden="true"></i></span><span><strong>Signalements à examiner</strong><small>En attente d’une première revue</small></span><b>{{ number_format($dashboard['attention']['reports']) }}</b></a>@endif
            @if($supportCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.support.index', ['status_group' => 'active']) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-life-ring" aria-hidden="true"></i></span><span><strong>Réclamations ouvertes</strong><small>Tickets nouvellement ouverts</small></span><b>{{ number_format($dashboard['attention']['tickets']) }}</b></a>@endif
            @if($paymentsCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.payments.index', ['transaction_status' => 'unknown', 'transaction_from' => $from->toDateString(), 'transaction_to' => $to->toDateString()]) }}"><span class="admin-dashboard__attention-icon is-red"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><span><strong>Paiements à vérifier</strong><small>Transactions au résultat incertain sur la période</small></span><b>{{ number_format($dashboard['attention']['payment_anomalies']) }}</b></a>@endif
            @if($reportsCanView && $dashboard['attention']['reviews'] > 0)<a class="admin-dashboard__attention-item" href="{{ route('admin.reports.index', ['status' => 'pending', 'target_type' => \App\Models\Review::class]) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i></span><span><strong>Avis signalés</strong><small>Signalements de contenu de type avis</small></span><b>{{ number_format($dashboard['attention']['reviews']) }}</b></a>@endif
        </div>
    </section>

    <div class="admin-dashboard__columns">
        @if($usersCanView || $prosCanView)<section class="admin-dashboard__card" aria-labelledby="admin-growth-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">COMMUNAUTÉ</p><h2 id="admin-growth-title">Évolution des inscriptions</h2><p>Clients et professionnels créés par jour sur la période.</p></div></div><div class="admin-dashboard__chart"><canvas id="admin-dashboard-growth" role="img" aria-label="Inscriptions clients et professionnels au fil du temps"></canvas></div>@if($communityChartTotal > 0)<div class="admin-dashboard__chart admin-dashboard__chart--compact"><h3 class="admin-dashboard__mini-heading">Répartition de la communauté</h3><canvas id="admin-dashboard-community" role="img" aria-label="Répartition entre clients et professionnels"></canvas></div>@else<p class="admin-dashboard__empty">Aucune communauté enregistrée pour le moment.</p>@endif</section>@endif
        <section class="admin-dashboard__card" aria-labelledby="admin-funnel-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">PARCOURS COMMERCIAL</p><h2 id="admin-funnel-title">Demandes vers commandes</h2><p>Demandes créées pendant la période, classées selon leur état actuel; les commandes terminées sont filtrées par date de finalisation.</p></div></div><div class="admin-dashboard__funnel">
            @foreach([['Demandes publiées', $dashboard['funnel']['requests'], $requestUrl(), 'requests.view'], ['Demandes au stade devis', $dashboard['funnel']['quotations'], $requestUrl('quoted'), 'requests.view'], ['Demandes acceptées', $dashboard['funnel']['accepted_quotations'], $requestUrl('accepted'), 'requests.view'], ['Commandes créées', $dashboard['funnel']['orders'], route('admin.orders.index', $range), 'orders.view'], ['Commandes de la cohorte terminées', $dashboard['funnel']['completed_orders_from_cohort'], route('admin.orders.index', ['status' => 'completed'] + $range), 'orders.view']] as [$label, $value, $url, $permission])
                @if(auth()->user()->hasPermissionTo($permission))<a href="{{ $url }}" class="admin-dashboard__funnel-step"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></a>@endif
            @endforeach
        </div>@if($requestsCanView && $ordersCanView)<div class="admin-dashboard__rates"><span>Demande → devis <b>{{ $dashboard['funnel']['request_to_quote_rate'] }}%</b></span><span>Devis acceptés <b>{{ $dashboard['funnel']['quote_acceptance_rate'] }}%</b></span><span>Finalisation de la cohorte <b>{{ $dashboard['funnel']['completion_rate'] }}%</b></span></div>@endif</section>
    </div>

    <div class="admin-dashboard__columns">
        @if($ordersCanView)<section class="admin-dashboard__card" aria-labelledby="admin-orders-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">OPÉRATIONS</p><h2 id="admin-orders-title">État actuel des commandes</h2></div><a href="{{ route('admin.orders.index') }}">Toutes les commandes</a></div>@if(collect($dashboard['charts']['order_statuses'])->isNotEmpty())<div class="admin-dashboard__chart admin-dashboard__chart--compact"><canvas id="admin-dashboard-orders" role="img" aria-label="Répartition des commandes actuelles par statut"></canvas></div>@else<p class="admin-dashboard__empty">Aucune commande enregistrée sur la plateforme.</p>@endif<div class="admin-dashboard__recent-orders"><h3>Commandes récentes</h3>@forelse($dashboard['recent_orders'] as $order)<a href="{{ route('admin.orders.show', $order) }}"><strong>{{ $order->order_number ?: 'CMD-'.$order->id }}</strong><span>{{ $order->client?->name ?? 'Client' }} · {{ $order->professional?->user?->name ?? 'Professionnel' }}</span><small>{{ str_replace('_', ' ', $order->status->value) }} · {{ $order->created_at?->format('d/m/Y H:i') }}</small></a>@empty<p>Aucune commande enregistrée.</p>@endforelse</div></section>@endif
        @if($paymentsCanView)<section class="admin-dashboard__card" aria-labelledby="admin-finance-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">FINANCES</p><h2 id="admin-finance-title">Finances et flux financiers</h2><p>Montants séparés par devise; les paiements ne sont pas assimilés aux soldes du portefeuille.</p></div><a href="{{ route('admin.payments.index') }}">Transactions</a></div>@forelse($dashboard['financial']['payment_volume'] as $currency => $volume)<div class="admin-dashboard__finance"><strong>{{ $currency }}</strong><div><span>Paiements confirmés</span><b><a href="{{ route('admin.payments.index', ['transaction_status' => 'succeeded', 'processed_from' => $from->toDateString(), 'processed_to' => $to->toDateString()]) }}">{{ number_format((float) $volume, 2, ',', ' ') }} {{ $currency }}</a></b></div><div><span>Commissions comptabilisées</span><b><a href="{{ route('admin.payments.index', ['commission_status' => 'posted', 'commission_from' => $from->toDateString(), 'commission_to' => $to->toDateString()]) }}">{{ number_format((float) ($dashboard['financial']['commissions'][$currency]['commission'] ?? 0), 2, ',', ' ') }} {{ $currency }}</a></b></div><div><span>Revenus nets professionnels enregistrés</span><b><a href="{{ route('admin.payments.index', ['commission_status' => 'posted', 'commission_from' => $from->toDateString(), 'commission_to' => $to->toDateString()]) }}">{{ number_format((float) ($dashboard['financial']['commissions'][$currency]['professional_net'] ?? 0), 2, ',', ' ') }} {{ $currency }}</a></b></div></div>@empty<p class="admin-dashboard__empty">Aucun paiement confirmé sur cette période.</p>@endforelse
            <p class="admin-dashboard__note"><a href="{{ route('admin.payments.index', ['transaction_status' => 'succeeded', 'processed_from' => $from->toDateString(), 'processed_to' => $to->toDateString()]) }}">Transactions confirmées : {{ number_format($dashboard['financial']['confirmed_transactions_count']) }}</a></p>
            <div class="admin-dashboard__status-list">@if($paymentsCanView)<a href="{{ route('admin.payments.index', ['transaction_status' => 'initiated', 'transaction_from' => $from->toDateString(), 'transaction_to' => $to->toDateString()]) }}"><span>Transactions initiées</span><strong>{{ $dashboard['payments']['initiated'] ?? 0 }}</strong></a><a href="{{ route('admin.payments.index', ['transaction_status' => 'pending', 'transaction_from' => $from->toDateString(), 'transaction_to' => $to->toDateString()]) }}"><span>Transactions en attente</span><strong>{{ $dashboard['payments']['pending'] ?? 0 }}</strong></a><a href="{{ route('admin.payments.index', ['transaction_status' => 'failed', 'transaction_from' => $from->toDateString(), 'transaction_to' => $to->toDateString()]) }}"><span>Transactions échouées</span><strong>{{ $dashboard['payments']['failed'] ?? 0 }}</strong></a><a href="{{ route('admin.payments.index', ['transaction_status' => 'unknown', 'transaction_from' => $from->toDateString(), 'transaction_to' => $to->toDateString()]) }}"><span>Résultat à vérifier</span><strong>{{ $dashboard['payments']['unknown'] ?? 0 }}</strong></a>@else<div><span>Transactions initiées</span><strong>{{ $dashboard['payments']['initiated'] ?? 0 }}</strong></div><div><span>Transactions en attente</span><strong>{{ $dashboard['payments']['pending'] ?? 0 }}</strong></div>@endif</div>
            @if(collect($dashboard['financial']['transaction_statuses'])->isNotEmpty())<div class="admin-dashboard__chart admin-dashboard__chart--compact"><canvas id="admin-dashboard-transactions" role="img" aria-label="Répartition des transactions par statut sur la période"></canvas></div>@else<p class="admin-dashboard__empty">Aucune transaction sur la période sélectionnée.</p>@endif
            @forelse($dashboard['financial']['trend'] as $currency => $trend)
                <div class="admin-dashboard__chart"><h3 class="admin-dashboard__mini-heading">Évolution financière — {{ $currency }}</h3><canvas id="admin-dashboard-finance-{{ \Illuminate\Support\Str::slug($currency) }}" data-financial-currency="{{ $currency }}" role="img" aria-label="Évolution des paiements et commissions en {{ $currency }}"></canvas></div>
            @empty
                <p class="admin-dashboard__empty">Aucune donnée financière quotidienne à comparer sur cette période.</p>
            @endforelse
            <div class="admin-dashboard__recent-orders"><h3>Transactions récentes sur la période</h3>@forelse($dashboard['recent_transactions'] as $transaction)<a href="{{ $transaction->payment ? route('admin.payments.show', $transaction->payment) : route('admin.payments.index', ['transaction_status' => $transaction->status->value, 'transaction_from' => $from->toDateString(), 'transaction_to' => $to->toDateString()]) }}"><strong>{{ $transaction->payment?->order?->order_number ?: 'Paiement #'.$transaction->payment_id }} · {{ number_format((float) $transaction->amount, 2, ',', ' ') }} {{ $transaction->currency }}</strong><span>{{ $transaction->payment?->client?->name ?? 'Client' }} · {{ $transaction->payment?->provider?->value ?? 'Fournisseur non précisé' }} · {{ str_replace('_', ' ', $transaction->status->value) }}</span><small>Transaction #{{ $transaction->id }} · {{ $transaction->created_at?->format('d/m/Y H:i') }}</small></a>@empty<p>Aucune transaction sur cette période.</p>@endforelse</div>
            <p class="admin-dashboard__note"><a href="{{ route('admin.payments.index', ['refund_status_group' => 'open']) }}">Remboursements ouverts : {{ number_format($dashboard['financial']['refunds_pending']) }}</a> · <a href="{{ route('admin.withdrawals.index', ['status_group' => 'pending_review']) }}">Retraits à examiner : {{ number_format($dashboard['financial']['withdrawals_pending'] + $dashboard['financial']['withdrawals_review']) }}</a>. Les flux sont suivis séparément.</p>
        </section>@endif
    </div>

    <div class="admin-dashboard__columns">
        @if($catalogAnalyticsCanView)<section class="admin-dashboard__card" aria-labelledby="admin-catalog-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">CATALOGUE</p><h2 id="admin-catalog-title">Demandes par catégorie</h2><p>Demandes créées sur la période, regroupées selon la catégorie du service demandé.</p></div></div>@if($dashboard['catalog']['requests_by_category']->isNotEmpty())<div class="admin-dashboard__chart"><canvas id="admin-dashboard-categories" role="img" aria-label="Demandes de service par catégorie"></canvas></div>@else<p class="admin-dashboard__empty">Aucune demande catégorisée sur la période.</p>@endif</section>@endif
        <section class="admin-dashboard__card" aria-labelledby="admin-activity-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">SANTÉ ET SUPERVISION</p><h2 id="admin-activity-title">État des services de la plateforme</h2><p>Éléments dont la source est enregistrée par l’application.</p></div></div><div class="admin-dashboard__status-list"><div><span>Notifications en échec</span><strong>{{ number_format($dashboard['health']['failed_notifications']) }}</strong></div><div><span>Notifications en attente</span><strong>{{ number_format($dashboard['health']['pending_notifications']) }}</strong></div>@if($paymentsCanView)<a href="{{ route('admin.payments.index', ['refund_status_group' => 'open']) }}"><span>Demandes de remboursement ouvertes</span><strong>{{ number_format($dashboard['financial']['refunds_pending']) }}</strong></a>@endif</div><p class="admin-dashboard__note">Les files système, intégrations externes et événements de sécurité ne sont pas exposés ici faute d’une source de supervision fiable dédiée.</p></section>
    </div>

    <section class="admin-dashboard__card" aria-labelledby="admin-professional-state"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">OFFRE PROFESSIONNELLE</p><h2 id="admin-professional-state">Professionnels et services publiés</h2><p>Vérification, visibilité et publication sont des états distincts.</p></div>@if($prosCanView)<a href="{{ route('admin.professionals.index') }}">Voir les professionnels</a>@endif</div>@if($prosCanView)<div class="admin-dashboard__status-grid">@foreach([['Vérifiés', $dashboard['professionals']['verification']['verified'], 'verified'], ['Avec service publié', $dashboard['community']['professionals_with_published_services'], 'has_service'], ['Sans service publié', $dashboard['community']['professionals_without_published_services'], 'no_service'], ['Services publiés', $dashboard['community']['published_services'], 'published']] as [$label, $value, $filter])@if($filter !== 'published' || $servicesCanView)<div class="admin-dashboard__status">@if($filter === 'verified' && $prosCanView)<a href="{{ route('admin.professionals.index', ['verification_status' => 'verified']) }}">{{ $label }}</a>@elseif($filter === 'has_service')<a href="{{ route('admin.professionals.index', ['has_published_service' => 'yes']) }}">{{ $label }}</a>@elseif($filter === 'no_service')<a href="{{ route('admin.professionals.index', ['has_published_service' => 'no']) }}">{{ $label }}</a>@elseif($filter === 'published' && $servicesCanView)<a href="{{ route('admin.services.index', ['status' => 'published']) }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif<strong>{{ number_format($value) }}</strong></div>@endif @endforeach</div>@else<p class="admin-dashboard__empty">Accès aux statistiques professionnelles non autorisé.</p>@endif</section>

<section class="admin-dashboard__columns">
    @if($catalogAnalyticsCanView)<section class="admin-dashboard__card" aria-labelledby="admin-top-services">
        <div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">PERFORMANCE DU CATALOGUE</p><h2 id="admin-top-services">Services les plus demandés</h2><p>Demandes reçues et commandes associées pendant la période sélectionnée.</p></div>@if(auth()->user()->hasPermissionTo('admin.services.view'))<a href="{{ route('admin.services.index') }}">Tous les services</a>@endif</div>
        @if($catalogAnalyticsCanView)
            <div class="admin-dashboard__table-wrap"><table class="admin-dashboard__table"><thead><tr><th>Service</th><th>Catégorie</th><th>Demandes</th><th>Commandes</th></tr></thead><tbody>
                @forelse($dashboard['catalog']['top_services'] as $service)
                    <tr><td><a href="{{ route('admin.services.show', $service) }}">{{ $service->title }}</a></td><td>{{ $service->category?->name ?? 'Sans catégorie' }}</td><td>{{ number_format($service->service_requests_count) }}</td><td>{{ number_format($service->orders_count) }}</td></tr>
                @empty<tr><td colspan="4" class="admin-dashboard__empty">Aucune activité de service sur cette période.</td></tr>@endforelse
            </tbody></table></div>
        @else
            <p class="admin-dashboard__empty">Tu ne disposes pas de la permission de consulter le catalogue.</p>
        @endif
        <p class="admin-dashboard__note">@if($categoriesCanView)<a href="{{ route('admin.categories.index') }}">Catégories actives : {{ number_format($dashboard['catalog']['active_categories']) }}</a>. @endif Le classement mesure la demande, pas le chiffre d’affaires.</p>
    </section>@endif
    <section class="admin-dashboard__card" aria-labelledby="admin-geography">
        <div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">COUVERTURE GÉOGRAPHIQUE</p><h2 id="admin-geography">Professionnels visibles par zone</h2><p>Répartition agrégée à partir des villes et provinces déclarées, sans coordonnées GPS privées.</p></div></div>
        @if($prosCanView)
            <h3 class="admin-dashboard__mini-heading">Professionnels visibles</h3>
            <div class="admin-dashboard__status-list">
                @forelse($dashboard['geography']['professionals_by_zone'] as $zone)
                    <a href="{{ route('admin.professionals.index', ['city' => $zone->city]) }}"><span>{{ $zone->city }}{{ $zone->province ? ' · '.$zone->province : '' }}</span><strong>{{ number_format($zone->professionals_count) }}</strong></a>
                @empty<p class="admin-dashboard__empty">Aucune zone renseignée pour les professionnels actuellement visibles.</p>@endforelse
            </div>
        @endif
        @if($usersCanView)
            <h3 class="admin-dashboard__mini-heading">Clients par adresse principale</h3>
            <div class="admin-dashboard__status-list">
                @forelse($dashboard['geography']['clients_by_zone'] as $zone)
                    <a href="{{ route('admin.users.index', ['role' => 'client', 'location' => $zone->city]) }}"><span>{{ $zone->city }}{{ $zone->province ? ' · '.$zone->province : '' }}</span><strong>{{ number_format($zone->clients_count) }}</strong></a>
                @empty<p class="admin-dashboard__empty">Aucune adresse principale exploitable pour les clients.</p>@endforelse
            </div>
        @endif
        @if(! $prosCanView && ! $usersCanView)
            <p class="admin-dashboard__empty">Tu ne disposes pas de la permission de consulter les données géographiques.</p>
        @endif
    </section>
</section>

<section class="admin-dashboard__card" aria-labelledby="admin-recent-activity">
    <div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">SUPERVISION MÉTIER</p><h2 id="admin-recent-activity">Activité récente</h2><p>Événements de création enregistrés pendant la période sélectionnée. Ce flux ne remplace pas le journal d’audit.</p></div></div>
    <div class="admin-dashboard__timeline">
        @php $visibleActivity = collect($dashboard['recent_activity'])->filter(fn ($item) => auth()->user()->hasPermissionTo($item['permission'])); @endphp
        @forelse($visibleActivity as $item)
            <a class="admin-dashboard__timeline-item" href="{{ $item['url'] }}">
                <span class="admin-dashboard__timeline-dot" aria-hidden="true"></span>
                <span class="admin-dashboard__timeline-content"><strong>{{ $item['type'] }} · {{ $item['title'] }}</strong><small>{{ $item['description'] }}</small></span>
                <time datetime="{{ $item['occurred_at']?->toIso8601String() }}">{{ $item['occurred_at']?->format('d/m/Y H:i') }}</time>
            </a>
        @empty
            <p class="admin-dashboard__empty">Aucune activité accessible à afficher pour cette période.</p>
        @endforelse
    </div>
</section>

</div>
@endsection
