@extends('layouts.admin')
@section('title', 'Prévisualisation de campagne')
@section('content')
<div class="admin-page"><h1>Prévisualisation : {{ $campaign->name }}</h1><section class="admin-card"><p><strong>Titre :</strong> {{ $campaign->subject }}</p><p>{{ $campaign->body }}</p><p><strong>Audience estimée :</strong> {{ number_format($recipientCount) }} compte(s) actif(s), avec adresse e-mail vérifiée.</p><p>Le segment est recalculé au lancement puis figé dans l’historique des destinataires.</p>
@if($recipientCount > 100 && !$campaign->approved_by)<p>Cette campagne exige l’approbation d’un autre administrateur avant l’envoi.</p><form method="POST" action="{{ route('admin.communication.campaigns.approve',$campaign) }}">@csrf<button>Approuver la campagne</button></form>@endif
<a href="{{ route('admin.communication.campaigns.index') }}">Retour</a></section></div>
@endsection
