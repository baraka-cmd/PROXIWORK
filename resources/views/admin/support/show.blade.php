@extends('layouts.admin')

@section('page_eyebrow', 'ADMINISTRATION / SUPPORT')
@section('page_title', 'Ticket #'.$ticket->id)
@section('page_description', $ticket->subject)

@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.support.index') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour</a>
@endsection

@section('content')
@if(session('success'))
    <div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>
@endif

<div class="admin-detail-grid">
    <section class="admin-card">
        <div class="admin-card__header">
            <div><span class="admin-card__eyebrow">Dossier</span><h3>{{ $ticket->subject }}</h3></div>
            <span class="admin-status admin-status--{{ $ticket->status->value }}">{{ str_replace('_', ' ', $ticket->status->value) }}</span>
        </div>
        <dl class="admin-detail-list">
            <div><dt>Utilisateur</dt><dd>{{ $ticket->user?->name }} · {{ $ticket->user?->email }}</dd></div>
            <div><dt>Catégorie</dt><dd>{{ $ticket->category->value }}</dd></div>
            <div><dt>Priorité</dt><dd>{{ $ticket->priority->value }}</dd></div>
            <div><dt>Créé le</dt><dd>{{ $ticket->created_at?->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Résolu le</dt><dd>{{ $ticket->resolved_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Clôturé le</dt><dd>{{ $ticket->closed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>
    </section>

    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Affectation</span><h3>{{ $ticket->assignee?->name ?? 'Non assigné' }}</h3></div></div>
        @if($ticket->status->value !== 'closed')
            <form method="POST" action="{{ route('admin.support.assign', $ticket) }}" class="admin-filter-grid">
                @csrf
                <label class="admin-field admin-field--wide"><span>Agent Support</span><select name="assigned_to" required><option value="">Choisir</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected($ticket->assigned_to === $assignee->id)>{{ $assignee->name }} — {{ $assignee->email }}</option>@endforeach</select></label>
                <div class="admin-filter-actions"><button class="button button--secondary" type="submit">Assigner</button></div>
            </form>
        @endif
    </section>
</div>

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Gestion</span><h3>Priorité et statut</h3></div></div>
    @if($ticket->status->value !== 'closed')
        <form method="POST" action="{{ route('admin.support.update', $ticket) }}" class="admin-filter-grid">
            @csrf @method('PATCH')
            <label class="admin-field"><span>Priorité</span><select name="priority"><option value="">Conserver</option>@foreach(['urgent'=>'Urgente','high'=>'Haute','normal'=>'Normale','low'=>'Basse'] as $value=>$label)<option value="{{ $value }}" @selected($ticket->priority->value === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="admin-field"><span>Statut</span><select name="status"><option value="">Conserver</option><option value="in_progress">En cours</option><option value="waiting">En attente</option></select></label>
            <div class="admin-filter-actions"><button class="button button--secondary" type="submit">Mettre à jour</button></div>
        </form>
    @endif
</section>

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Conversation</span><h3>Historique des messages</h3></div></div>
    <div class="admin-timeline">
        @forelse($ticket->messages as $message)
            <article class="admin-timeline__item">
                <div class="admin-timeline__dot"></div>
                <div><strong>{{ $message->sender?->name ?? 'Utilisateur' }}</strong><p>{{ $message->created_at?->format('d/m/Y H:i') }}</p><div class="admin-message">{{ $message->body }}</div></div>
            </article>
        @empty
            <p class="admin-muted">Aucun message.</p>
        @endforelse
    </div>
</section>

@if($ticket->status->value !== 'closed')
<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Réponse</span><h3>Répondre à l'utilisateur</h3></div></div>
    <form method="POST" action="{{ route('admin.support.message', $ticket) }}">
        @csrf
        <label class="admin-field"><span>Message</span><textarea name="body" rows="6" maxlength="10000" required placeholder="Réponse professionnelle au demandeur..."></textarea></label>
        <button class="button button--primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Envoyer la réponse</button>
    </form>
</section>

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Workflow</span><h3>Finalisation</h3></div></div>
    <div class="admin-action-row">
        <form method="POST" action="{{ route('admin.support.resolve', $ticket) }}">@csrf<button class="button button--primary" type="submit">Marquer comme résolu</button></form>
    </div>
</section>
@elseif($ticket->status->value === 'resolved')
<section class="admin-card">
    <div class="admin-action-row"><form method="POST" action="{{ route('admin.support.close', $ticket) }}">@csrf<button class="button button--primary" type="submit">Clôturer définitivement</button></form></div>
</section>
@endif
@endsection
