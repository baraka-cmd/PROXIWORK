@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / AUDIT')
@section('page_title', 'Journal d’audit')
@section('page_description', 'Trace en lecture seule des actions administratives et sensibles de la plateforme.')

@section('content')
<section class="admin-card">
    <div class="admin-card__header">
        <div>
            <span class="admin-card__eyebrow">Traçabilité</span>
            <h3>{{ $logs->total() }} événement{{ $logs->total() > 1 ? 's' : '' }}</h3>
        </div>
    </div>

    <form method="GET" class="admin-filter-grid">
        <label class="admin-field admin-field--wide">
            <span>Recherche</span>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Action, acteur, cible, IP...">
        </label>
        <label class="admin-field">
            <span>Action exacte</span>
            <input type="text" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="admin.support.replied">
        </label>
        <label class="admin-field">
            <span>Utilisateur ID</span>
            <input type="number" name="user_id" min="1" value="{{ $filters['user_id'] ?? '' }}">
        </label>
        <label class="admin-field">
            <span>Type de cible</span>
            <input type="text" name="subject_type" value="{{ $filters['subject_type'] ?? '' }}" placeholder="App\Models\User">
        </label>
        <label class="admin-field">
            <span>ID cible</span>
            <input type="number" name="subject_id" min="1" value="{{ $filters['subject_id'] ?? '' }}">
        </label>
        <label class="admin-field">
            <span>IP</span>
            <input type="text" name="ip_address" value="{{ $filters['ip_address'] ?? '' }}" placeholder="192.168.1.10">
        </label>
        <label class="admin-field">
            <span>Du</span>
            <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
        </label>
        <label class="admin-field">
            <span>Au</span>
            <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
        </label>
        <div class="admin-filter-actions">
            <button class="button button--primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrer</button>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Acteur</th><th>Action</th><th>Cible</th><th>IP</th><th></th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? 'Système' }}<small class="admin-table__muted">{{ $log->user?->email ?? '—' }}</small></td>
                    <td><strong>{{ $log->action }}</strong></td>
                    <td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                    <td><a class="admin-icon-button" href="{{ route('admin.audit.show', $log) }}" aria-label="Voir le détail de l’audit"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="admin-empty"><i class="fa-solid fa-clock-rotate-left"></i><strong>Aucun événement</strong><span>Aucun journal ne correspond aux critères.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="admin-pagination">{{ $logs->links() }}</div>@endif
</section>
@endsection
