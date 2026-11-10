@extends('layouts.admin')
@section('title', 'Centre de notifications')
@section('content')
<div class="admin-page"><h1>Centre de notifications</h1><p>Consultation des notifications internes générées pour les utilisateurs. Le statut « lue » est distinct de la remise des e-mails, SMS ou notifications push.</p>
<div class="admin-stats-grid"><article class="admin-stat-card"><span>Total</span><strong>{{ number_format($summary['total']) }}</strong></article><article class="admin-stat-card"><span>Non lues</span><strong>{{ number_format($summary['unread']) }}</strong></article><article class="admin-stat-card"><span>Lues</span><strong>{{ number_format($summary['read']) }}</strong></article></div>
<form method="GET" action="{{ route('admin.notifications.index') }}" class="admin-filter-form">
<label>Recherche <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Destinataire, ID notification ou utilisateur"></label>
<label>État <select name="status"><option value="">Tous</option><option value="unread" @selected(($filters['status'] ?? '') === 'unread')>Non lue</option><option value="read" @selected(($filters['status'] ?? '') === 'read')>Lue</option></select></label>
<label>Événement <select name="action"><option value="">Tous</option>@foreach(['account','service_request','quotation','order','payment','withdrawal','message','review','communication'] as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></label>
<button>Filtrer</button><a href="{{ route('admin.notifications.index') }}">Réinitialiser</a></form>
<section class="admin-card"><table class="admin-table"><thead><tr><th>Notification</th><th>Destinataire</th><th>Événement</th><th>Priorité</th><th>État</th><th>Créée le</th><th></th></tr></thead><tbody>
@forelse($notifications as $item)<tr><td>{{ $item->data['title'] ?? 'Sans titre' }}<small>{{ $item->id }}</small></td><td>{{ $item->recipient_name }} <small>ID {{ $item->user_id }}</small></td><td>{{ $item->data['action'] ?? '—' }}</td><td>{{ $item->data['priority'] ?? 'normal' }}</td><td>{{ $item->read_at ? 'Lue' : 'Non lue' }}</td><td>{{ $item->created_at ?? '—' }}</td><td><a href="{{ route('admin.notifications.show', $item->id) }}">Détails</a></td></tr>@empty<tr><td colspan="7">Aucune notification ne correspond aux filtres.</td></tr>@endforelse
</tbody></table>{{ $notifications->links() }}</section></div>
@endsection
