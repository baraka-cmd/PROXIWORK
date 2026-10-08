@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / AUDIT')
@section('page_title', 'Événement #'.$log->id)
@section('page_description', 'Détail immuable du journal d’audit.')

@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.audit.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour</a>
@endsection

@section('content')
<section class="admin-card">
    <div class="admin-card__header">
        <div><span class="admin-card__eyebrow">Événement</span><h3>{{ $log->action }}</h3></div>
        <span class="admin-page-badge"><i class="fa-solid fa-lock" aria-hidden="true"></i> Lecture seule</span>
    </div>

    <dl class="admin-detail-list">
        <div><dt>Date</dt><dd>{{ $log->created_at?->format('d/m/Y H:i:s T') }}</dd></div>
        <div><dt>Acteur</dt><dd>{{ $log->user?->name ?? 'Système' }} · {{ $log->user?->email ?? '—' }}</dd></div>
        <div><dt>Cible</dt><dd>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</dd></div>
        <div><dt>Adresse IP</dt><dd>{{ $log->ip_address ?? '—' }}</dd></div>
        <div><dt>User-Agent</dt><dd>{{ $log->user_agent ?? '—' }}</dd></div>
    </dl>
</section>

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Métadonnées</span><h3>Contexte enregistré</h3></div></div>
    @if($log->metadata)
        <pre class="admin-code-block">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    @else
        <p class="admin-muted">Aucune métadonnée enregistrée.</p>
    @endif
</section>
@endsection
