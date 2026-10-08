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
</div>
@endsection
