@extends('layouts.admin')
@section('page_eyebrow','ADMINISTRATION / MODÉRATION')
@section('page_title','Litige #'.$dispute->id)
@section('page_description','Dossier contradictoire lié à la commande '.($dispute->order?->order_number ?? '#'.$dispute->order_id).'.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.disputes.index') }}">Retour aux litiges</a><section class="admin-card"><h3>Pièces justificatives privées</h3><ul>@forelse($dispute->attachments as $attachment)<li><a href="{{ route('admin.disputes.attachments.download', [$dispute, $attachment]) }}">{{ $attachment->original_name }}</a> — {{ number_format($attachment->size / 1024, 1) }} Ko · {{ $attachment->mime_type }} · ajouté par {{ $attachment->uploader?->name }}</li>@empty<li>Aucune preuve déposée.</li>@endforelse</ul></section>

@endsection
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="admin-detail-grid">
<section class="admin-card"><h3>Parties et commande</h3><dl class="admin-detail-list">
<div><dt>Client</dt><dd>{{ $dispute->client?->name }} · {{ $dispute->client?->email }}</dd></div>
<div><dt>Professionnel</dt><dd>{{ $dispute->professional?->business_name ?: $dispute->professional?->user?->name }}</dd></div>
<div><dt>Commande</dt><dd>{{ $dispute->order?->order_number }} · {{ $dispute->order?->status?->value }}</dd></div>
<div><dt>Motif</dt><dd>{{ $dispute->reason_code }}</dd></div>
<div><dt>Description initiale</dt><dd>{{ $dispute->description }}</dd></div>
<div><dt>Ouvert par</dt><dd>{{ $dispute->opener?->name }}</dd></div>
<div><dt>Statut</dt><dd>{{ $dispute->status->value }}</dd></div>
</dl></section>
<section class="admin-card"><h3>Affectation</h3><p>{{ $dispute->assignee?->name ?? 'Non assigné' }}</p>
<form method="POST" action="{{ route('admin.disputes.assign',$dispute) }}">@csrf
<label class="admin-field"><span>Responsable</span><select name="assigned_to" required><option value="">Choisir</option>@foreach($assignees as $a)<option value="{{ $a->id }}" @selected($dispute->assigned_to === $a->id)>{{ $a->name }}</option>@endforeach</select></label>
<button class="button button--secondary">Attribuer</button></form></section></div>
<section class="admin-card"><h3>Historique du dossier</h3><div class="admin-timeline">
@foreach($dispute->events as $event)<article class="admin-timeline__item"><div class="admin-timeline__dot"></div><div><strong>{{ str_replace('_',' ',$event->event_type) }}</strong><p>{{ $event->actor?->name ?? 'Système' }} · {{ $event->created_at?->format('d/m/Y H:i') }}</p>@if($event->reason)<p>{{ $event->reason }}</p>@endif</div></article>@endforeach
</div></section>
<section class="admin-card"><h3>Échanges des parties et notes internes</h3><div class="admin-timeline">
@foreach($dispute->messages as $message)<article class="admin-timeline__item"><div class="admin-timeline__dot"></div><div><strong>{{ $message->sender?->name }} @if($message->visibility === 'internal')<span class="admin-status">Interne</span>@endif</strong><p>{{ $message->created_at?->format('d/m/Y H:i') }}</p><div class="admin-message">{{ $message->body }}</div></div></article>@endforeach
</div>@if($dispute->status->isActive())<form method="POST" action="{{ route('admin.disputes.message',$dispute) }}">@csrf
<label class="admin-field"><span>Message / note</span><textarea name="body" required minlength="2" maxlength="10000" rows="4"></textarea></label>
<label class="admin-field"><span>Visibilité</span><select name="visibility"><option value="participants">Visible aux deux parties</option><option value="internal">Note interne uniquement</option></select></label>
<button class="button button--primary">Ajouter au dossier</button></form>@endif</section>
@if($dispute->status->value !== 'closed')
<section class="admin-card"><h3>Faire avancer le dossier</h3><form method="POST" action="{{ route('admin.disputes.transition',$dispute) }}" class="admin-filter-grid">@csrf
<label class="admin-field"><span>Nouveau statut</span><select name="status" required>@foreach(($dispute->status->value === 'resolved' ? ['closed'] : ['under_review','awaiting_client','awaiting_professional','mediation','decision_pending','closed']) as $s)<option value="{{ $s }}">{{ str_replace('_',' ',$s) }}</option>@endforeach</select></label>
<label class="admin-field admin-field--wide"><span>Motif obligatoire</span><textarea name="reason" minlength="10" maxlength="2000" required></textarea></label><button class="button button--secondary">Enregistrer la transition</button></form></section>@endif
@if($dispute->status->value === 'closed')
<section class="admin-card"><h3>Réouverture exceptionnelle</h3><p>La réouverture doit être justifiée et restera visible dans l’historique.</p>
<form method="POST" action="{{ route('admin.disputes.reopen',$dispute) }}">@csrf
<label class="admin-field"><span>Motif de réouverture</span><textarea name="reason" minlength="20" maxlength="2000" required></textarea></label>
<button class="button button--secondary">Rouvrir le litige</button></form></section>
@endif
@if($dispute->status->value === 'decision_pending')
<section class="admin-card"><h3>Décision motivée</h3><form method="POST" action="{{ route('admin.disputes.decide',$dispute) }}" class="admin-filter-grid">@csrf
<label class="admin-field"><span>Résultat</span><select name="resolution_code" required><option value="client_favor">En faveur du client</option><option value="professional_favor">En faveur du professionnel</option><option value="amicable_agreement">Accord amiable</option><option value="no_breach">Aucun manquement établi</option><option value="financial_review_required">Examen financier requis (sans exécution financière)</option><option value="other">Autre</option></select></label>
<label class="admin-field admin-field--wide"><span>Décision et justification</span><textarea name="resolution_note" minlength="20" maxlength="4000" required rows="5"></textarea></label><button class="button button--primary">Enregistrer la décision</button></form></section>@endif
@endsection
