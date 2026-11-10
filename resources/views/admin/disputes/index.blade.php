@extends('layouts.admin')
@section('page_eyebrow','ADMINISTRATION / MODÉRATION')
@section('page_title','Litiges commerciaux')
@section('page_description','Examinez les désaccords liés aux commandes, attribuez les dossiers et suivez leur résolution.')
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
<section class="admin-stats-grid">
@foreach(['open'=>'Nouveaux','under_review'=>'En examen','awaiting_parties'=>'En attente des parties','mediation'=>'Médiation','decision_pending'=>'Décision attendue','resolved'=>'Résolus'] as $key=>$label)<div class="admin-stat-card"><span>{{ $label }}</span><strong>{{ $overview[$key] }}</strong></div>@endforeach
</section>
<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Dossiers</span><h3>{{ $disputes->total() }} litige(s)</h3></div></div>
<form method="GET" class="admin-filter-grid">
<label class="admin-field admin-field--wide"><span>Recherche</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Référence commande, client, motif"></label>
<label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(['open','under_review','awaiting_client','awaiting_professional','mediation','decision_pending','resolved','closed','reopened'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ str_replace('_',' ',$s) }}</option>@endforeach</select></label>
<label class="admin-field"><span>Priorité</span><select name="priority"><option value="">Toutes</option>@foreach(['critical','high','normal','low'] as $p)<option value="{{ $p }}" @selected(($filters['priority'] ?? '') === $p)>{{ $p }}</option>@endforeach</select></label>
<label class="admin-field"><span>Responsable</span><select name="assigned_to"><option value="">Tous</option>@foreach($assignees as $a)<option value="{{ $a->id }}" @selected((string)($filters['assigned_to'] ?? '') === (string)$a->id)>{{ $a->name }}</option>@endforeach</select></label>
<div class="admin-filter-actions"><button class="button button--primary">Filtrer</button></div></form>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Dossier</th><th>Commande</th><th>Client</th><th>Professionnel</th><th>Priorité</th><th>Statut</th><th>Responsable</th><th></th></tr></thead><tbody>
@forelse($disputes as $d)<tr><td><strong>#{{ $d->id }}</strong><small class="admin-table__muted">{{ $d->reason_code }}</small></td><td>{{ $d->order?->order_number ?? '#'.$d->order_id }}</td><td>{{ $d->client?->name }}</td><td>{{ $d->professional?->business_name ?: $d->professional?->user?->name }}</td><td>{{ $d->priority->value }}</td><td>{{ str_replace('_',' ',$d->status->value) }}</td><td>{{ $d->assignee?->name ?? 'Non assigné' }}</td><td><a class="admin-icon-button" href="{{ route('admin.disputes.show',$d) }}" aria-label="Ouvrir le litige"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td></tr>
@empty<tr><td colspan="8"><div class="admin-empty"><strong>Aucun litige</strong><span>Aucun dossier ne correspond aux critères.</span></div></td></tr>@endforelse
</tbody></table></div>@if($disputes->hasPages())<div class="admin-pagination">{{ $disputes->links() }}</div>@endif</section>
@endsection
