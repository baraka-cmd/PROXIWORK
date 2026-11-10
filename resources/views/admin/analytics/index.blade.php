@extends('layouts.admin')
@push('head')
    @unless(app()->environment('testing'))
        @vite(['resources/css/pages/admin/analytics.css','resources/js/pages/admin/analytics.js'])
    @endunless
@endpush

@section('page_eyebrow', 'ADMINISTRATION / ANALYTICS')
@section('page_title', 'Analytics')
@section('page_description', 'Évolution de l’activité, des opérations et des finances sur une période contrôlée.')

@section('page_actions')
<div class="admin-analytics-actions"><a class="button button--secondary button--small" href="{{ route('admin.analytics.export', request()->query()) }}">Exporter le rapport CSV</a></div>
<form class="admin-analytics-period" method="GET" action="{{ route('admin.analytics') }}">
    <div class="admin-analytics-period__presets">
        @foreach(['7d'=>'7 jours','30d'=>'30 jours','90d'=>'90 jours','365d'=>'1 an'] as $value => $label)
            <button class="button button--{{ $range === $value ? 'primary' : 'secondary' }} button--small" type="submit" name="range" value="{{ $value }}">{{ $label }}</button>
        @endforeach
        <button class="button button--secondary button--small" type="button" data-analytics-custom>Personnalisée</button>
    </div>
    <div class="admin-analytics-period__custom" data-analytics-custom-fields @if($range !== 'custom') hidden @endif>
        <input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="Date de début">
        <span>→</span>
        <input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="Date de fin">
        <input type="hidden" name="range" value="custom">
        <button class="button button--primary button--small" type="submit">Actualiser</button>
    </div>
</form>
@endsection

@section('content')
<div class="admin-analytics" data-analytics='@json($analytics)'>
    <section class="admin-analytics__kpis">
        @foreach([
            ['Nouveaux utilisateurs',$analytics['totals']['users'],'fa-users'],
            ['Nouveaux professionnels',$analytics['totals']['professionals'],'fa-user-tie'],
            ['Services créés',$analytics['totals']['services'],'fa-briefcase'],
            ['Demandes créées',$analytics['totals']['requests'],'fa-file-lines'],
            ['Commandes créées',$analytics['totals']['orders'],'fa-cart-shopping'],
            ['Paiements réussis',$analytics['totals']['successful_payments'],'fa-circle-check'],
        ] as [$label,$value,$icon])
            <article class="admin-analytics__kpi"><div class="admin-analytics__kpi-icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></div><div><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div></article>
        @endforeach
    </section>
    <section class="admin-analytics__card"><div class="admin-analytics__card-header"><span class="admin-analytics__eyebrow">Croissance</span><h2>Acquisition et catalogue</h2><div class="admin-analytics__chart"><canvas id="admin-analytics-growth" aria-label="Évolution de la croissance" role="img"></canvas></div></div></section>
    <section class="admin-analytics__card"><div class="admin-analytics__card-header"><span class="admin-analytics__eyebrow">Opérations</span><h2>Demandes et commandes</h2><div class="admin-analytics__chart"><canvas id="admin-analytics-operations" aria-label="Évolution des opérations" role="img"></canvas></div></div></section>
    <section class="admin-analytics__card"><div class="admin-analytics__card-header"><span class="admin-analytics__eyebrow">Support</span><h2>Tickets créés et résolus</h2><div class="admin-analytics__chart"><canvas id="admin-analytics-support" aria-label="Évolution du support" role="img"></canvas></div></div></section>
    <section class="admin-analytics__card"><div class="admin-analytics__card-header"><span class="admin-analytics__eyebrow">Finances</span><h2>Paiements et commissions</h2><div class="admin-analytics__chart"><canvas id="admin-analytics-finance" aria-label="Évolution financière" role="img"></canvas></div></div></section>

    <section class="admin-analytics__card">
        <div class="admin-analytics__card-header">
            <span class="admin-analytics__eyebrow">Parcours commercial</span>
            <h2>Demandes, devis et commandes</h2>
            <div class="admin-analytics__kpis">
                <article class="admin-analytics__kpi"><div><span>Demandes éligibles</span><strong>{{ number_format($analytics['commercial']['requests']['total']) }}</strong></div></article>
                <article class="admin-analytics__kpi"><div><span>Demandes avec devis</span><strong>{{ number_format($analytics['commercial']['requests']['with_quotation']) }}</strong></div></article>
                <article class="admin-analytics__kpi"><div><span>Demandes avec commande</span><strong>{{ number_format($analytics['commercial']['requests']['with_order']) }}</strong></div></article>
                <article class="admin-analytics__kpi"><div><span>Commandes terminées dans la période</span><strong>{{ number_format($analytics['commercial']['orders']['completed_in_period']) }}</strong></div></article>
            </div>
            <p>Taux demande → devis : {{ number_format($analytics['commercial']['conversion']['request_to_quotation_percent'], 1) }} % · Demande → commande : {{ number_format($analytics['commercial']['conversion']['request_to_order_percent'], 1) }} % · Acceptation des devis : {{ number_format($analytics['commercial']['conversion']['quotation_acceptance_percent'], 1) }} %</p>
            <p>{{ $analytics['commercial']['methodology'] }}</p>
        </div>
    </section>
    <section class="admin-analytics__card">
        <div class="admin-analytics__card-header"><span class="admin-analytics__eyebrow">Catalogue</span><h2>Performance des catégories</h2></div>
        <div class="table-responsive"><table class="admin-table"><thead><tr><th>Catégorie</th><th>Services</th><th>Demandes</th><th>Commandes</th></tr></thead><tbody>
        @forelse($analytics['catalog']['categories'] as $category)
            <tr><td>{{ $category['name'] }}</td><td>{{ number_format($category['services']) }}</td><td>{{ number_format($category['requests']) }}</td><td>{{ number_format($category['orders']) }}</td></tr>
        @empty
            <tr><td colspan="4">Aucune donnée pour cette période.</td></tr>
        @endforelse
        </tbody></table></div>
        <p>Services publiés : {{ number_format($analytics['catalog']['published_services']) }} · Services actuellement éligibles : {{ number_format($analytics['catalog']['requestable_services']) }} · Professionnels publics disposant d’un service éligible : {{ number_format($analytics['catalog']['public_professionals_with_requestable_services']) }}</p>
        <p>{{ $analytics['catalog']['methodology'] }}</p>
    </section>
    <section class="admin-analytics__card">
        <div class="admin-analytics__card-header"><span class="admin-analytics__eyebrow">Remboursements</span><h2>Remboursements réussis</h2>
        @forelse($analytics['financial']['refunds'] as $currency => $refund)
            <p>{{ $currency }} : {{ number_format($refund['amount'], 2, ',', ' ') }} ({{ $refund['count'] }} opérations)</p>
        @empty
            <p>Aucun remboursement réussi sur la période.</p>
        @endforelse
        </div>
    </section>
    <section class="admin-analytics__card">
        <div class="admin-analytics__card-header"><span class="admin-analytics__eyebrow">Géographie</span><h2>Répartition de l’offre professionnelle</h2></div>
        <div class="table-responsive"><table class="admin-table"><thead><tr><th>Province</th><th>Ville</th><th>Professionnels publics</th><th>Demandes</th><th>Commandes</th></tr></thead><tbody>
        @forelse($analytics['geography']['professional_base_locations'] as $location)
            <tr><td>{{ $location['province'] }}</td><td>{{ $location['city'] }}</td><td>{{ $location['suppressed'] ? 'Données masquées' : number_format($location['professionals']) }}</td><td>{{ number_format($location['requests']) }}</td><td>{{ number_format($location['orders']) }}</td></tr>
        @empty
            <tr><td colspan="5">Aucune localisation professionnelle exploitable.</td></tr>
        @endforelse
        </tbody></table></div>
        <p>{{ $analytics['geography']['location_basis'] }} Les effectifs inférieurs à trois professionnels sont masqués.</p>
    </section>
</div>
@endsection
