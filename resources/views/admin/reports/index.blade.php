@extends('layouts.admin')

@section('page_eyebrow','ADMINISTRATION / MODÉRATION')
@section('page_title','Signalements')
@section('page_description','Traitez les signalements, suivez leur priorité et appliquez les actions de modération autorisées.')
@section('page_actions')<span class="admin-page-badge"><i class="fa-solid fa-flag"></i> Centre de modération</span>@endsection

@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('success') }}</div>@endif
@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i>{{ $errors->first() }}</div>@endif

<section class="admin-card">
<div class="admin-card__header"><div><span class="admin-card__eyebrow">Recherche</span><h3>Filtrer les signalements</h3></div><a class="admin-link" href="{{ route('admin.reports.index') }}">Réinitialiser</a></div>
<form method="GET" class="admin-filter-grid">
<label class="admin-field admin-field--wide"><span>Recherche</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Motif, description, reporter"></label>
<label class="admin-field"><span>Statut</span><select name="status"><option value="">Tous</option>@foreach(['pending'=>'En attente','under_review'=>'En revue','resolved'=>'Résolu','dismissed'=>'Classé sans suite'] as $v=>$l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></label>
<label class="admin-field"><span>Priorité</span><select name="priority"><option value="">Toutes</option>@foreach(['critical'=>'Critique','high'=>'Haute','normal'=>'Normale','low'=>'Basse'] as $v=>$l)<option value="{{ $v }}" @selected(($filters['priority'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></label>
<label class="admin-field"><span>Du</span><input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></label>
<label class="admin-field"><span>Au</span><input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}"></label>
<div class="admin-filter-actions"><button class="button button--primary" type="submit"><i class="fa-solid fa-filter"></i> Appliquer</button></div>
</form>
</section>

<section class="admin-card">
<div class="admin-card__header"><div><span class="admin-card__eyebrow">File de traitement</span><h3>{{ $reports->total() }} signalement{{ $reports->total() > 1 ? 's' : '' }}</h3></div></div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Signalement</th><th>Cible</th><th>Priorité</th><th>Statut</th><th>Assigné</th><th>Date</th><th></th></tr></thead><tbody>
@forelse($reports as $report)
<tr>
<td><strong>{{ $report->reason_code }}</strong><small class="admin-table__muted">{{ \Illuminate\Support\Str::limit($report->description ?? 'Sans description', 70) }}</small><small class="admin-table__muted">par {{ $report->reporter?->name ?? '—' }}</small></td>
<td>{{ class_basename($report->target_type) }} #{{ $report->target_id }}</td>
<td><span class="admin-status admin-status--{{ $report->priority->value }}">{{ $report->priority->value }}</span></td>
<td><span class="admin-status admin-status--{{ $report->status->value }}">{{ ['pending'=>'En attente','under_review'=>'En revue','resolved'=>'Résolu','dismissed'=>'Classé sans suite'][$report->status->value] }}</span></td>
<td>{{ $report->assignee?->name ?? 'Non assigné' }}</td>
<td>{{ $report->created_at?->format('d/m/Y H:i') }}</td>
<td><a class="admin-icon-button" href="{{ route('admin.reports.show',$report) }}" aria-label="Voir le signalement"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
</tr>
@empty
<tr><td colspan="7"><div class="admin-empty"><i class="fa-solid fa-flag"></i><strong>Aucun signalement</strong><span>La file de modération est vide pour ces critères.</span></div></td></tr>
@endforelse
</tbody></table></div>
@if($reports->hasPages())<div class="admin-pagination">{{ $reports->links() }}</div>@endif
</section>
@endsection