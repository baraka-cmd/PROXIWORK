@extends('layouts.admin')
@section('page_eyebrow','COMMUNICATION / HISTORIQUE')
@section('page_title','Détail d’une livraison')
@section('page_description','Le résultat d’un canal ne doit pas être confondu avec la lecture effective du message.')
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success">{{ session('success') }}</div>@endif
<section class="admin-card"><dl class="admin-detail-grid">
<dt>Destinataire</dt><dd>{{ $delivery->user?->name }} — {{ $delivery->user?->email }}</dd>
<dt>Canal</dt><dd>{{ $delivery->channel }}</dd><dt>Statut</dt><dd>{{ $delivery->status }}</dd>
<dt>Tentatives</dt><dd>{{ $delivery->attempts }}</dd><dt>Référence fournisseur</dt><dd>{{ $delivery->provider_reference ?: '—' }}</dd>
<dt>Code d’erreur</dt><dd>{{ $delivery->last_error_code ?: '—' }}</dd>
<dt>Créée</dt><dd>{{ $delivery->created_at?->format('d/m/Y H:i:s') }}</dd>
<dt>Envoyée</dt><dd>{{ $delivery->sent_at?->format('d/m/Y H:i:s') ?: '—' }}</dd>
<dt>Notification</dt><dd>{{ data_get($delivery->notification?->data,'title','—') }}</dd>
<dt>Message</dt><dd>{{ data_get($delivery->notification?->data,'message','—') }}</dd>
</dl>
@if($delivery->status === 'FAILED')<form method="POST" action="{{ route('admin.communication.notifications.retry',$delivery) }}" onsubmit="return confirm('Relancer cette livraison explicitement échouée ?')">@csrf<button class="button button--primary">Demander une relance</button></form>@elseif($delivery->status === 'UNKNOWN')<p class="admin-alert">Résultat incertain : ne pas relancer sans rapprochement avec le fournisseur.</p>@endif
<a class="button button--secondary" href="{{ route('admin.communication.notifications') }}">Retour à l’historique</a></section>
@endsection