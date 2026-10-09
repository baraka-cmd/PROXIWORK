@extends('layouts.admin')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/dashboard.css', 'resources/js/pages/admin/dashboard.js'])
    @endunless
@endpush
@section('page_eyebrow','PILOTAGE DE LA PLATEFORME')
@section('page_title','Dashboard')
@section('page_description','Vue opérationnelle de l’activité de PROXIWORK sur la période sélectionnée.')
@section('page_actions')
<form class="admin-dashboard-period" method="GET" action="{{ route('admin.dashboard') }}"><input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="Date de début"><span>→</span><input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="Date de fin"><button class="button button--primary button--small">Actualiser</button></form>
@endsection
@section('content')
<div class="admin-dashboard">
<section class="admin-dashboard__kpis">
@foreach([['Utilisateurs',$dashboard['users']['total'],'fa-users'],['Professionnels',$dashboard['professionals']['total'],'fa-user-tie'],['Clients',$dashboard['clients']['total'],'fa-user-group'],['Vérifications',$dashboard['professionals']['verification']['pending']+$dashboard['professionals']['verification']['under_review'],'fa-user-check']] as [$label,$value,$icon])
<article class="admin-dashboard__kpi"><div class="admin-dashboard__kpi-icon"><i class="fa-solid {{ $icon }}"></i></div><div><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div></article>
@endforeach
</section>
<div class="admin-dashboard__grid">
<section class="admin-dashboard__card admin-dashboard__card--wide"><div class="admin-dashboard__card-header"><h2>Vérification professionnelle</h2><a href="{{ route('admin.professionals.index') }}">Professionnels</a></div><div class="admin-dashboard__status-grid">@foreach($dashboard['professionals']['verification'] as $status=>$count)<div class="admin-dashboard__status"><span>{{ str_replace('_',' ',ucfirst($status)) }}</span><strong>{{ number_format($count) }}</strong></div>@endforeach</div></section>
<section class="admin-dashboard__card"><div class="admin-dashboard__card-header"><h2>Services</h2><a href="{{ route('admin.services.index') }}">Voir</a></div><div class="admin-dashboard__status-list">@foreach($dashboard['services'] as $status=>$count)<div><span>{{ str_replace('_',' ',ucfirst($status)) }}</span><strong>{{ number_format($count) }}</strong></div>@endforeach</div></section>
<section class="admin-dashboard__card"><div class="admin-dashboard__card-header"><h2>Demandes</h2><a href="{{ route('admin.service-requests.index') }}">Voir</a></div><div class="admin-dashboard__status-list">@foreach($dashboard['requests'] as $status=>$count)<div><span>{{ str_replace('_',' ',ucfirst($status)) }}</span><strong>{{ number_format($count) }}</strong></div>@endforeach</div></section>
<section class="admin-dashboard__card admin-dashboard__card--wide"><div class="admin-dashboard__card-header"><h2>Commandes</h2><a href="{{ route('admin.orders.index') }}">Voir</a></div><div class="admin-dashboard__status-grid">@forelse($dashboard['orders'] as $status=>$count)<div class="admin-dashboard__status"><span>{{ str_replace('_',' ',ucfirst($status)) }}</span><strong>{{ number_format($count) }}</strong></div>@empty<p>Aucune commande.</p>@endforelse</div></section>
<section class="admin-dashboard__card admin-dashboard__card--wide"><div class="admin-dashboard__card-header"><h2>Finances</h2><a href="{{ route('admin.payments.index') }}">Paiements</a></div>@forelse($dashboard['financial']['payment_volume'] as $currency=>$volume)<div class="admin-dashboard__finance"><strong>{{ $currency }}</strong><div><span>Volume paiements</span><b>{{ number_format((float)$volume,2) }} {{ $currency }}</b></div>@if(isset($dashboard['financial']['commissions'][$currency]))<div><span>Commission</span><b>{{ number_format((float)$dashboard['financial']['commissions'][$currency]['commission'],2) }} {{ $currency }}</b></div>@endif</div>@empty<p>Aucun paiement réussi sur la période.</p>@endforelse</section>
</div></div>
@endsection