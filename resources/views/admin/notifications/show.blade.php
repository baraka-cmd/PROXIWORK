@extends('layouts.admin')
@section('title', 'Détail de notification')
@section('content')
<div class="admin-page"><div class="admin-page-header"><div><h1>{{ $notification->data['title'] ?? 'Notification sans titre' }}</h1><p>Notification interne — {{ $notification->id }}</p></div><a href="{{ route('admin.notifications.index') }}">Retour au centre</a></div>
<section class="admin-card"><dl class="admin-detail-list">
<dt>Destinataire</dt><dd>{{ $notification->recipient_name }} (ID {{ $notification->user_id }})</dd>
<dt>Événement</dt><dd>{{ $notification->data['action'] ?? '—' }}</dd>
<dt>Priorité</dt><dd>{{ $notification->data['priority'] ?? 'normal' }}</dd>
<dt>État de lecture</dt><dd>{{ $notification->read_at ? 'Lue le '.$notification->read_at : 'Non lue' }}</dd>
<dt>Créée le</dt><dd>{{ $notification->created_at }}</dd>
<dt>URL interne</dt><dd>{{ $notification->data['url'] ?? '—' }}</dd>
<dt>Message</dt><dd>{{ $notification->data['message'] ?? 'Contenu non disponible' }}</dd>
</dl></section><p>Pour le résultat de remise externe et les tentatives du fournisseur, consulter l’historique des livraisons.</p><a href="{{ route('admin.notifications.deliveries.index') }}">Historique des livraisons</a></div>
@endsection
