@extends('layouts.admin')

@section('page_eyebrow','ADMINISTRATION / MODÉRATION')
@section('page_title','Signalement #'.$report->id)
@section('page_description','Dossier de modération et historique des actions.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.reports.index') }}"><i class="fa-solid fa-arrow-left"></i> Signalements</a>@endsection

@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status"><i class="fa-solid fa-circle-check"></i>{{ session('success') }}</div>@endif
@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert"><i class="fa-solid fa-triangle-exclamation"></i>{{ $errors->first() }}</div>@endif

<div class="admin-detail-grid">
<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Dossier</span><h3>{{ $report->reason_code }}</h3></div><span class="admin-status admin-status--{{ $report->status->value }}">{{ $report->status->value }}</span></div>
<dl class="admin-detail-list"><div><dt>Reporter</dt><dd>{{ $report->reporter?->name }} · {{ $report->reporter?->email }}</dd></div><div><dt>Cible</dt><dd>{{ class_basename($report->target_type) }} #{{ $report->target_id }}</dd></div><div><dt>Priorité</dt><dd>{{ $report->priority->value }}</dd></div><div><dt>Créé</dt><dd>{{ $report->created_at?->format('d/m/Y H:i') }}</dd></div><div><dt>Description</dt><dd>{{ $report->description ?: 'Aucune description.' }}</dd></div></dl></section>

<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Affectation</span><h3>{{ $report->assignee?->name ?? 'Non assigné' }}</h3></div></div>
<form method="POST" action="{{ route('admin.reports.assign',$report) }}" class="admin-filter-grid">@csrf<label class="admin-field admin-field--wide"><span>Administrateur / modérateur</span><select name="assigned_to" required><option value="">Choisir un modérateur</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected($report->assigned_to === $assignee->id)>{{ $assignee->name }} — {{ $assignee->email }}</option>@endforeach</select></label><div class="admin-filter-actions"><button class="button button--secondary" type="submit">Assigner</button></div></form>
</section>
</div>

<section class="admin-card">
<div class="admin-card__header"><div><span class="admin-card__eyebrow">Workflow</span><h3>Actions disponibles</h3></div></div>
<div class="admin-action-row">
@if($report->status->value === 'pending')
<form method="POST" action="{{ route('admin.reports.start-review',$report) }}">@csrf<button class="button button--primary" type="submit">Démarrer la revue</button></form>
@elseif($report->status->value === 'under_review')
<form method="POST" action="{{ route('admin.reports.moderate',$report) }}">@csrf<input type="hidden" name="action_type" value="warning"><button class="button button--secondary" type="submit">Avertissement</button></form>
@php($targetClass=class_basename($report->target_type))
@if($targetClass === 'Review')<form method="POST" action="{{ route('admin.reports.moderate',$report) }}">@csrf<input type="hidden" name="action_type" value="hide_review"><button class="button button--secondary" type="submit">Masquer l’avis</button></form>@endif
@if($targetClass === 'Service')<form method="POST" action="{{ route('admin.reports.moderate',$report) }}">@csrf<input type="hidden" name="action_type" value="unpublish_service"><button class="button button--secondary" type="submit">Dépublier le service</button></form>@endif
@if($targetClass === 'User')<form method="POST" action="{{ route('admin.reports.moderate',$report) }}">@csrf<input type="hidden" name="action_type" value="suspend_user"><button class="button button--secondary" type="submit">Suspendre l’utilisateur</button></form>@endif
@if($targetClass === 'ProfessionalProfile')<form method="POST" action="{{ route('admin.reports.moderate',$report) }}">@csrf<input type="hidden" name="action_type" value="suspend_professional"><button class="button button--secondary" type="submit">Suspendre le professionnel</button></form>@endif
<form method="POST" action="{{ route('admin.reports.resolve',$report) }}">@csrf<textarea name="resolution_note" required maxlength="2000" rows="2" placeholder="Note de résolution"></textarea><button class="button button--primary" type="submit">Résoudre</button></form>
<form method="POST" action="{{ route('admin.reports.dismiss',$report) }}">@csrf<textarea name="resolution_note" required maxlength="2000" rows="2" placeholder="Pourquoi classer sans suite ?"></textarea><button class="button button--secondary" type="submit">Classer sans suite</button></form>
@endif
</div></section>

<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Historique</span><h3>Actions de modération</h3></div></div>
<div class="admin-timeline">@forelse($report->moderationActions as $action)<article class="admin-timeline__item"><div class="admin-timeline__dot"></div><div><strong>{{ $action->action_type->value }}</strong><p>{{ $action->moderator?->name ?? 'Administration' }} · {{ $action->created_at?->format('d/m/Y H:i') }}</p>@if($action->note)<p>{{ $action->note }}</p>@endif</div></article>@empty<p class="admin-muted">Aucune action appliquée.</p>@endforelse</div>
@if($report->resolution_note)<p><strong>Résolution :</strong> {{ $report->resolution_note }}</p>@endif
</section>
@endsection