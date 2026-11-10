@extends('layouts.admin')
@section('page_eyebrow','COMMUNICATION / NOTIFICATIONS')
@section('page_title','Notifications et livraisons')
@section('page_description','Suivez les livraisons réelles par canal. Une notification interne peut produire plusieurs tentatives externes.')
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
<div class="admin-stats-grid">
@foreach(['En attente'=>'pending','En cours'=>'processing','Envoyées'=>'sent','Échecs / incertaines'=>'failed'] as $label=>$key)<article class="admin-stat-card"><span>{{ $label }}</span><strong>{{ $summary[$key] }}</strong></article>@endforeach
</div>
<section class="admin-card"><form method="GET" class="admin-filter-grid">
<label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(['PENDING','PROCESSING','RETRYING','SENT','DELIVERED','FAILED','SKIPPED','SIMULATED','UNKNOWN'] as $status)<option @selected(($filters['status']??'')===$status)>{{ $status }}</option>@endforeach</select></label>
<label class="admin-field"><span>Canal</span><select name="channel"><option value="">Tous</option>@foreach(['mail','sms','push'] as $channel)<option @selected(($filters['channel']??'')===$channel)>{{ $channel }}</option>@endforeach</select></label>
<label class="admin-field"><span>Utilisateur</span><input name="search" value="{{ $filters['search']??'' }}" maxlength="100"></label>
<button class="button button--primary">Filtrer</button>
</form></section>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Destinataire</th><th>Notification</th><th>Canal</th><th>Statut</th><th>Tentatives</th><th>Dernière mise à jour</th><th></th></tr></thead><tbody>
@forelse($deliveries as $delivery)<tr><td>{{ $delivery->user?->name ?? 'Compte supprimé' }}<small>{{ $delivery->user?->email }}</small></td><td>{{ data_get($delivery->notification?->data,'title','Notification') }}</td><td>{{ strtoupper($delivery->channel) }}</td><td><span class="admin-status">{{ $delivery->status }}</span></td><td>{{ $delivery->attempts }}</td><td>{{ $delivery->updated_at?->format('d/m/Y H:i') }}</td><td><a class="admin-link" href="{{ route('admin.communication.notifications.show',$delivery) }}">Détails</a></td></tr>@empty<tr><td colspan="7">Aucune livraison trouvée.</td></tr>@endforelse
</tbody></table></div><div class="admin-pagination">{{ $deliveries->links() }}</div></section>
@endsection