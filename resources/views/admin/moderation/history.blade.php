@extends('layouts.admin')
@section('page_eyebrow','ADMINISTRATION / TRAÇABILITÉ')
@section('page_title','Historique des interventions')
@section('page_description','Journal transversal des actions de modération, de support et de résolution des litiges.')
@section('content')
<section class="admin-card"><form method="GET" class="admin-filter-grid">
<label class="admin-field admin-field--wide"><span>Recherche</span><input name="search" value="{{ $filters['search']??'' }}" placeholder="Action, ressource, identifiant, acteur"></label>
<label class="admin-field"><span>Du</span><input type="date" name="from" value="{{ $filters['from']??'' }}"></label><label class="admin-field"><span>Au</span><input type="date" name="to" value="{{ $filters['to']??'' }}"></label><button class="button button--primary">Filtrer</button></form></section>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Acteur</th><th>Action</th><th>Ressource</th><th>Détails</th></tr></thead><tbody>
@forelse($logs as $log)<tr><td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $log->user?->name ?? 'Système' }}</td><td>{{ $log->action }}</td><td>{{ class_basename($log->subject_type ?? '—') }} #{{ $log->subject_id ?? '—' }}</td><td>{{ \Illuminate\Support\Str::limit(json_encode($log->metadata, JSON_UNESCAPED_UNICODE),180) }}</td></tr>
@empty<tr><td colspan="5">Aucune intervention trouvée.</td></tr>@endforelse
</tbody></table></div>@if($logs->hasPages())<div class="admin-pagination">{{ $logs->links() }}</div>@endif</section>
@endsection
