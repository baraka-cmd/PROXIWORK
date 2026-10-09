@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / SUPPORT')
@section('page_title', 'Support')
@section('page_description', 'Supervisez les tickets, priorités, affectations et délais de traitement.')

@section('content')
@if(session('success'))
    <div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>
@endif

<section class="admin-card">
    <div class="admin-card__header">
        <div>
            <span class="admin-card__eyebrow">File de support</span>
            <h3>{{ $tickets->total() }} ticket{{ $tickets->total() > 1 ? 's' : '' }}</h3>
        </div>
    </div>

    <form method="GET" class="admin-filter-grid">
        <label class="admin-field admin-field--wide">
            <span>Recherche</span>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Sujet, nom ou e-mail">
        </label>
        <label class="admin-field">
            <span>Statut</span>
            <select name="status">
                <option value="">Tous</option>
                @foreach(['open'=>'Ouvert','in_progress'=>'En cours','waiting'=>'En attente','resolved'=>'Résolu','closed'=>'Clôturé'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>Priorité</span>
            <select name="priority">
                <option value="">Toutes</option>
                @foreach(['urgent'=>'Urgente','high'=>'Haute','normal'=>'Normale','low'=>'Basse'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>Catégorie</span>
            <select name="category">
                <option value="">Toutes</option>
                @foreach(['ACCOUNT'=>'Compte','PAYMENT'=>'Paiement','ORDER'=>'Commande','PROFESSIONAL'=>'Professionnel','SERVICE'=>'Service','VERIFICATION'=>'Vérification','TECHNICAL'=>'Technique','OTHER'=>'Autre'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>Assigné à</span>
            <select name="assigned_to">
                <option value="">Tous</option>
                @foreach($assignees as $assignee)
                    <option value="{{ $assignee->id }}" @selected((string)($filters['assigned_to'] ?? '') === (string)$assignee->id)>{{ $assignee->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="admin-filter-actions">
            <button class="button button--primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrer</button>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Ticket</th><th>Catégorie</th><th>Priorité</th><th>Statut</th><th>Assigné</th><th>Dernier message</th><th></th></tr></thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td>
                        <a href="{{ route('admin.support.show', $ticket) }}"><strong>#{{ $ticket->id }} · {{ $ticket->subject }}</strong></a>
                        <small class="admin-table__muted">{{ $ticket->user?->name }} · {{ $ticket->messages_count }} message(s)</small>
                    </td>
                    <td>{{ $ticket->category->value }}</td>
                    <td><span class="admin-status admin-status--{{ $ticket->priority->value }}">{{ $ticket->priority->value }}</span></td>
                    <td><span class="admin-status admin-status--{{ $ticket->status->value }}">{{ str_replace('_', ' ', $ticket->status->value) }}</span></td>
                    <td>{{ $ticket->assignee?->name ?? 'Non assigné' }}</td>
                    <td>{{ $ticket->last_message_at?->format('d/m/Y H:i') ?? $ticket->created_at?->format('d/m/Y H:i') }}</td>
                    <td><a class="admin-icon-button" href="{{ route('admin.support.show', $ticket) }}" aria-label="Ouvrir le ticket"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="admin-empty"><i class="fa-solid fa-headset"></i><strong>Aucun ticket</strong><span>Aucun ticket ne correspond aux filtres.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($tickets->hasPages())<div class="admin-pagination">{{ $tickets->links() }}</div>@endif
</section>
@endsection
