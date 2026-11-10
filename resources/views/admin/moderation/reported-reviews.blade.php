@extends('layouts.admin')
@section('page_eyebrow','ADMINISTRATION / MODÉRATION')
@section('page_title','Avis signalés')
@section('page_description','File dédiée aux signalements dont la cible est un avis client.')
@section('content')
<section class="admin-card"><div class="admin-card__header"><h3>{{ $reports->total() }} signalement(s) lié(s) aux avis</h3><a class="admin-link" href="{{ route('admin.moderation.reviews') }}">Tous les avis</a></div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Signalement</th><th>Auteur du signalement</th><th>Avis concerné</th><th>Statut</th><th>Priorité</th><th></th></tr></thead><tbody>
@forelse($reports as $report)<tr><td>#{{ $report->id }} · {{ $report->reason_code }}<small>{{ $report->description }}</small></td><td>{{ $report->reporter?->name }}</td><td>@if($report->target)Avis #{{ $report->target->id }} · {{ $report->target->rating }}/5 · {{ \Illuminate\Support\Str::limit($report->target->comment,70) }}@elseCible indisponible @endif</td><td>{{ $report->status->value }}</td><td>{{ $report->priority->value }}</td><td><a class="button button--secondary" href="{{ route('admin.reports.show',$report) }}">Examiner</a></td></tr>
@empty<tr><td colspan="6">Aucun avis signalé.</td></tr>@endforelse
</tbody></table></div>@if($reports->hasPages())<div class="admin-pagination">{{ $reports->links() }}</div>@endif</section>
@endsection
