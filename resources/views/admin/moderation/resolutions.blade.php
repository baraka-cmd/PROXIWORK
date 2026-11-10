@extends('layouts.admin')
@section('page_eyebrow','ADMINISTRATION / PILOTAGE')
@section('page_title','Résolutions et décisions')
@section('page_description','Vue consolidée des dossiers traités et encore ouverts. Les volumes ne remplacent pas l’examen des dossiers.')
@section('content')
<div class="admin-detail-grid">
<section class="admin-card"><h3>Signalements</h3><dl class="admin-detail-list"><div><dt>À traiter</dt><dd>{{ $reports['pending'] }}</dd></div><div><dt>Résolus</dt><dd>{{ $reports['resolved'] }}</dd></div><div><dt>Classés sans suite</dt><dd>{{ $reports['dismissed'] }}</dd></div></dl><a class="button button--secondary" href="{{ route('admin.reports.index') }}">Consulter les signalements</a></section>
<section class="admin-card"><h3>Réclamations / support</h3><dl class="admin-detail-list"><div><dt>Ouverts ou en cours</dt><dd>{{ $tickets['open'] }}</dd></div><div><dt>Résolus</dt><dd>{{ $tickets['resolved'] }}</dd></div><div><dt>Clôturés</dt><dd>{{ $tickets['closed'] }}</dd></div></dl><a class="button button--secondary" href="{{ route('admin.support.index') }}">Consulter le support</a></section>
<section class="admin-card"><h3>Litiges commerciaux</h3><dl class="admin-detail-list"><div><dt>Actifs</dt><dd>{{ $disputes['active'] }}</dd></div><div><dt>Résolus</dt><dd>{{ $disputes['resolved'] }}</dd></div><div><dt>Clôturés</dt><dd>{{ $disputes['closed'] }}</dd></div></dl><a class="button button--secondary" href="{{ route('admin.disputes.index') }}">Consulter les litiges</a></section>
</div>
<p class="admin-muted">Les indicateurs de délai de traitement seront ajoutés après validation des données historiques et des règles de calcul. Aucun délai moyen n’est estimé à partir de données incomplètes.</p>
@endsection
