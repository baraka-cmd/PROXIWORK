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
    $ordersUrl = fn (string $status) => route('admin.orders.index', $range + ['status' => $status]);
    $usersCanView = auth()->user()->hasPermissionTo('admin.users.view');
    $prosCanView = auth()->user()->hasPermissionTo('admin.professionals.view');
    $ordersCanView = auth()->user()->hasPermissionTo('orders.view');
    $paymentsCanView = auth()->user()->hasPermissionTo('payments.view');
    $reportsCanView = auth()->user()->hasPermissionTo('reports.manage');
    $supportCanView = auth()->user()->hasPermissionTo('support.manage');
    $withdrawalsCanView = auth()->user()->hasPermissionTo('admin.withdrawals.manage');
@endphp
<div class="admin-dashboard" data-admin-dashboard
    data-charts="{{ json_encode([
        'labels' => $dashboard['charts']['labels'],
        'registrations' => $dashboard['charts']['registrations'],
        'orderStatuses' => collect($dashboard['charts']['order_statuses'])->map(fn ($count, $status) => ['label' => str_replace('_', ' ', $status), 'value' => $count, 'url' => $ordersUrl($status)])->values(),
        'categories' => $dashboard['catalog']['requests_by_category']->map(fn ($row) => ['label' => $row->name, 'value' => (int) $row->requests_count, 'url' => route('admin.services.index', ['category_id' => $row->id])])->values(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
    <div class="admin-dashboard__meta"><span>Du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</span><span>Données actualisées à {{ \Illuminate\Support\Carbon::parse($dashboard['updated_at'])->format('d/m/Y H:i') }}</span></div>

    <section aria-labelledby="admin-platform-kpis" class="admin-dashboard__section">
        <div class="admin-dashboard__section-heading"><div><p class="admin-dashboard__eyebrow">APERÇU</p><h2 id="admin-platform-kpis">La plateforme en un coup d’œil</h2><p>Les comptes sont des cumuls actuels; les inscriptions et opérations sont limitées à la période choisie.</p></div></div>
        <div class="admin-dashboard__kpis">
            @if($usersCanView)<a class="admin-dashboard__kpi" href="{{ route('admin.users.index') }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Utilisateurs inscrits</span><strong>{{ number_format($dashboard['users']['total']) }}</strong><small>{{ number_format($dashboard['users']['new']) }} nouveaux sur la période</small></span></a>@endif
            @if($usersCanView)<a class="admin-dashboard__kpi" href="{{ route('admin.users.index', ['role' => 'client']) }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Clients</span><strong>{{ number_format($dashboard['clients']['total']) }}</strong><small>{{ number_format($dashboard['clients']['new']) }} nouveaux sur la période</small></span></a>@endif
            @if($prosCanView)<a class="admin-dashboard__kpi" href="{{ route('admin.professionals.index') }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Professionnels</span><strong>{{ number_format($dashboard['professionals']['total']) }}</strong><small>{{ number_format($dashboard['professionals']['new']) }} nouveaux sur la période</small></span></a>@endif
            @if($usersCanView)<a class="admin-dashboard__kpi admin-dashboard__kpi--warning" href="{{ route('admin.users.index', ['account_status' => 'suspended']) }}"><span class="admin-dashboard__kpi-icon"><i class="fa-solid fa-user-lock" aria-hidden="true"></i></span><span class="admin-dashboard__kpi-body"><span>Comptes suspendus</span><strong>{{ number_format($dashboard['community']['suspended']) }}</strong><small>État actuel des comptes</small></span></a>@endif
        </div>
    </section>

    <section class="admin-dashboard__section" aria-labelledby="admin-attention-title">
        <div class="admin-dashboard__section-heading"><div><p class="admin-dashboard__eyebrow">INTERVENTION</p><h2 id="admin-attention-title">À traiter maintenant</h2><p>Dossiers ouverts selon les statuts réellement utilisés par PROXIWORK.</p></div></div>
        <div class="admin-dashboard__attention">
            @if($prosCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.verification.index', ['verification_status' => 'pending']) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span><span><strong>Vérifications en attente</strong><small>Dossiers nécessitant une décision</small></span><b>{{ number_format($dashboard['attention']['verification']) }}</b></a>@endif
            @if($withdrawalsCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.withdrawals.index', ['status' => 'requested']) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i></span><span><strong>Retraits à examiner</strong><small>Demandes en attente ou à vérifier</small></span><b>{{ number_format($dashboard['attention']['withdrawals']) }}</b></a>@endif
            @if($reportsCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.reports.index', ['status' => 'pending']) }}"><span class="admin-dashboard__attention-icon is-red"><i class="fa-solid fa-flag" aria-hidden="true"></i></span><span><strong>Signalements à examiner</strong><small>En attente d’une première revue</small></span><b>{{ number_format($dashboard['attention']['reports']) }}</b></a>@endif
            @if($supportCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.support.index', ['status' => 'open']) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-life-ring" aria-hidden="true"></i></span><span><strong>Réclamations ouvertes</strong><small>Tickets nouvellement ouverts</small></span><b>{{ number_format($dashboard['attention']['tickets']) }}</b></a>@endif
            @if($paymentsCanView)<a class="admin-dashboard__attention-item" href="{{ route('admin.payments.index', ['transaction_status' => 'unknown'] + $range) }}"><span class="admin-dashboard__attention-icon is-red"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><span><strong>Paiements à vérifier</strong><small>Transactions au résultat incertain sur la période</small></span><b>{{ number_format($dashboard['attention']['payment_anomalies']) }}</b></a>@endif
            @if($reportsCanView && $dashboard['attention']['reviews'] > 0)<a class="admin-dashboard__attention-item" href="{{ route('admin.reports.index', ['status' => 'pending', 'target_type' => \App\Models\Review::class]) }}"><span class="admin-dashboard__attention-icon is-amber"><i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i></span><span><strong>Avis signalés</strong><small>Signalements de contenu de type avis</small></span><b>{{ number_format($dashboard['attention']['reviews']) }}</b></a>@endif
        </div>
    </section>

    <div class="admin-dashboard__columns">
        <section class="admin-dashboard__card" aria-labelledby="admin-growth-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">COMMUNAUTÉ</p><h2 id="admin-growth-title">Évolution des inscriptions</h2><p>Clients et professionnels créés par jour sur la période.</p></div></div><div class="admin-dashboard__chart"><canvas id="admin-dashboard-growth" role="img" aria-label="Inscriptions clients et professionnels au fil du temps"></canvas></div></section>
        <section class="admin-dashboard__card" aria-labelledby="admin-funnel-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">PARCOURS COMMERCIAL</p><h2 id="admin-funnel-title">Demandes vers commandes</h2><p>Étapes créées pendant la période; les taux sont calculés sur leur dénominateur correspondant.</p></div></div><div class="admin-dashboard__funnel">
            @foreach([['Demandes publiées', $dashboard['funnel']['requests'], route('admin.service-requests.index')], ['Devis créés', $dashboard['funnel']['quotations'], route('admin.service-requests.index')], ['Devis acceptés', $dashboard['funnel']['accepted_quotations'], route('admin.service-requests.index')], ['Commandes créées', $dashboard['funnel']['orders'], route('admin.orders.index', $range)], ['Commandes terminées', $dashboard['funnel']['completed_orders'], $ordersUrl('completed')]] as [$label, $value, $url])
                <a href="{{ $url }}" class="admin-dashboard__funnel-step"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></a>
            @endforeach
        </div><div class="admin-dashboard__rates"><span>Demande → devis <b>{{ $dashboard['funnel']['request_to_quote_rate'] }}%</b></span><span>Devis acceptés <b>{{ $dashboard['funnel']['quote_acceptance_rate'] }}%</b></span><span>Commandes terminées <b>{{ $dashboard['funnel']['completion_rate'] }}%</b></span></div></section>
    </div>

    <div class="admin-dashboard__columns">
        @if($ordersCanView)<section class="admin-dashboard__card" aria-labelledby="admin-orders-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">OPÉRATIONS</p><h2 id="admin-orders-title">État actuel des commandes</h2></div><a href="{{ route('admin.orders.index') }}">Toutes les commandes</a></div><div class="admin-dashboard__chart admin-dashboard__chart--compact"><canvas id="admin-dashboard-orders" role="img" aria-label="Répartition des commandes actuelles par statut"></canvas></div><div class="admin-dashboard__recent-orders"><h3>Commandes récentes</h3>@forelse($dashboard['recent_orders'] as $order)<a href="{{ route('admin.orders.show', $order) }}"><strong>{{ $order->order_number ?: 'CMD-'.$order->id }}</strong><span>{{ $order->client?->name ?? 'Client' }} · {{ $order->professional?->user?->name ?? 'Professionnel' }}</span><small>{{ str_replace('_', ' ', $order->status->value) }} · {{ $order->created_at?->format('d/m/Y H:i') }}</small></a>@empty<p>Aucune commande enregistrée.</p>@endforelse</div></section>@endif
        @if($paymentsCanView)<section class="admin-dashboard__card" aria-labelledby="admin-finance-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">FINANCES</p><h2 id="admin-finance-title">Finances et flux financiers</h2><p>Montants séparés par devise; les paiements ne sont pas assimilés aux soldes du portefeuille.</p></div><a href="{{ route('admin.payments.index') }}">Transactions</a></div>@forelse($dashboard['financial']['payment_volume'] as $currency => $volume)<div class="admin-dashboard__finance"><strong>{{ $currency }}</strong><div><span>Paiements confirmés</span><b>{{ number_format((float) $volume, 2, ',', ' ') }} {{ $currency }}</b></div><div><span>Commissions comptabilisées</span><b>{{ number_format((float) ($dashboard['financial']['commissions'][$currency]['commission'] ?? 0), 2, ',', ' ') }} {{ $currency }}</b></div><div><span>Revenus nets professionnels enregistrés</span><b>{{ number_format((float) ($dashboard['financial']['commissions'][$currency]['professional_net'] ?? 0), 2, ',', ' ') }} {{ $currency }}</b></div></div>@empty<p class="admin-dashboard__empty">Aucun paiement confirmé sur cette période.</p>@endforelse
            <div class="admin-dashboard__status-list">@if($paymentsCanView)<a href="{{ route('admin.payments.index', ['transaction_status' => 'initiated']) }}"><span>Transactions initiées</span><strong>{{ $dashboard['payments']['initiated'] ?? 0 }}</strong></a><a href="{{ route('admin.payments.index', ['transaction_status' => 'pending']) }}"><span>Transactions en attente</span><strong>{{ $dashboard['payments']['pending'] ?? 0 }}</strong></a><a href="{{ route('admin.payments.index', ['transaction_status' => 'failed']) }}"><span>Transactions échouées</span><strong>{{ $dashboard['payments']['failed'] ?? 0 }}</strong></a><a href="{{ route('admin.payments.index', ['transaction_status' => 'unknown']) }}"><span>Résultat à vérifier</span><strong>{{ $dashboard['payments']['unknown'] ?? 0 }}</strong></a>@else<div><span>Transactions initiées</span><strong>{{ $dashboard['payments']['initiated'] ?? 0 }}</strong></div><div><span>Transactions en attente</span><strong>{{ $dashboard['payments']['pending'] ?? 0 }}</strong></div>@endif</div>
            <div class="admin-dashboard__recent-orders"><h3>Transactions récentes sur la période</h3>@forelse($dashboard['recent_transactions'] as $transaction)<a href="{{ route('admin.payments.index', ['transaction_status' => $transaction->status->value]) }}"><strong>{{ $transaction->payment?->order?->order_number ?: 'Paiement #'.$transaction->payment_id }}</strong><span>{{ $transaction->payment?->client?->name ?? 'Client' }} · {{ str_replace('_', ' ', $transaction->status->value) }}</span><small>{{ $transaction->created_at?->format('d/m/Y H:i') }}</small></a>@empty<p>Aucune transaction sur cette période.</p>@endforelse</div>
            <p class="admin-dashboard__note">Remboursements en attente : {{ number_format($dashboard['financial']['refunds_pending']) }} · Retraits à examiner : {{ number_format($dashboard['financial']['withdrawals_pending']) }}. Les remboursements et retraits sont suivis séparément.</p>
        </section>@endif
    </div>

    <div class="admin-dashboard__columns">
        <section class="admin-dashboard__card" aria-labelledby="admin-catalog-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">CATALOGUE</p><h2 id="admin-catalog-title">Demandes par catégorie</h2><p>Demandes créées sur la période, regroupées selon la catégorie du service demandé.</p></div></div>@if($dashboard['catalog']['requests_by_category']->isNotEmpty())<div class="admin-dashboard__chart"><canvas id="admin-dashboard-categories" role="img" aria-label="Demandes de service par catégorie"></canvas></div>@else<p class="admin-dashboard__empty">Aucune demande catégorisée sur la période.</p>@endif</section>
        <section class="admin-dashboard__card" aria-labelledby="admin-activity-title"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">SANTÉ ET SUPERVISION</p><h2 id="admin-activity-title">État des services de la plateforme</h2><p>Éléments dont la source est enregistrée par l’application.</p></div></div><div class="admin-dashboard__status-list"><div><span>Notifications en échec</span><strong>{{ number_format($dashboard['health']['failed_notifications']) }}</strong></div><div><span>Notifications en attente</span><strong>{{ number_format($dashboard['health']['pending_notifications']) }}</strong></div><div><span>Demandes de remboursement en attente</span><strong>{{ number_format($dashboard['financial']['refunds_pending']) }}</strong></div></div><p class="admin-dashboard__note">Les files système, intégrations externes et événements de sécurité ne sont pas exposés ici faute d’une source de supervision fiable dédiée.</p></section>
    </div>

    <section class="admin-dashboard__card" aria-labelledby="admin-professional-state"><div class="admin-dashboard__card-header"><div><p class="admin-dashboard__eyebrow">OFFRE PROFESSIONNELLE</p><h2 id="admin-professional-state">Professionnels et services publiés</h2><p>Vérification, visibilité et publication sont des états distincts.</p></div>@if($prosCanView)<a href="{{ route('admin.professionals.index') }}">Voir les professionnels</a>@endif</div><div class="admin-dashboard__status-grid">@foreach([['Vérifiés', $dashboard['professionals']['verification']['verified'], 'verified'], ['Avec service publié', $dashboard['community']['professionals_with_published_services'], null], ['Sans service publié', $dashboard['community']['professionals_without_published_services'], null], ['Services publiés', $dashboard['community']['published_services'], 'published']] as [$label, $value, $filter])<div class="admin-dashboard__status">@if($filter === 'verified' && $prosCanView)<a href="{{ route('admin.professionals.index', ['verification_status' => 'verified']) }}">{{ $label }}</a>@elseif($filter === 'published')<a href="{{ route('admin.services.index', ['status' => 'published']) }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif<strong>{{ number_format($value) }}</strong></div>@endforeach</div></section>
</div>
@endsection
