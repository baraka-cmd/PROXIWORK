@extends('layouts.admin')
@section('page_eyebrow','PLATEFORME / SERVICE')
@section('page_title',$service->title)
@section('page_description',$service->short_description ?: 'Détail, publication et modération du service.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.services.index') }}"><i class="fa-solid fa-arrow-left"></i> Services</a>@endsection
@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/services-requests.css', 'resources/js/pages/admin/services-requests.js'])
    @endunless
@endpush
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="admin-detail-grid">
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Service</span><h3>Informations principales</h3></div><span class="admin-status admin-status--{{ $service->status->value }}">{{ ['draft'=>'Brouillon','published'=>'Publié','unpublished'=>'Dépublié','archived'=>'Archivé'][$service->status->value] }}</span></div>
        <dl class="admin-detail-list">
            <div><dt>Professionnel</dt><dd>{{ $service->professionalProfile->user->name ?? '—' }}</dd></div>
            <div><dt>Catégorie</dt><dd>{{ $service->category->parent?->name ? $service->category->parent->name.' / ' : '' }}{{ $service->category->name ?? '—' }}</dd></div>
            <div><dt>Tarification</dt><dd>{{ $service->pricing_type->value }} · {{ $service->currency }}</dd></div>
            <div><dt>Prix</dt><dd>{{ $service->price ?? ($service->price_min.' — '.$service->price_max) ?? 'Sur devis' }}</dd></div>
            <div><dt>Durée estimée</dt><dd>{{ $service->estimated_duration_minutes ? $service->estimated_duration_minutes.' min' : '—' }}</dd></div>
            <div><dt>Demandes historiques</dt><dd>{{ $service->service_requests_count }}</dd></div>
            <div><dt>Publication</dt><dd>{{ $service->status->value }}</dd></div>
            <div><dt>Modération</dt><dd>{{ $service->moderation_status->value }}</dd></div>
            <div><dt>Dernière décision</dt><dd>{{ $service->moderated_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Modérateur</dt><dd>{{ $service->moderator?->name ?? '—' }}</dd></div>
        </dl>
        @if($service->moderation_reason)<p><strong>Motif communiqué :</strong> {{ $service->moderation_reason }}</p>@endif
        <div class="admin-action-row">
            @can('adminManage',$service)
                @if($service->status->value==='published')
                    <form method="POST" action="{{ route('admin.services.unpublish',$service) }}" data-confirm="Dépublier ce service ?">@csrf<button class="button button--secondary" type="submit">Dépublier</button></form>
                @elseif(in_array($service->status->value,['draft','unpublished']) && $service->moderation_status->value === 'clear')
                    <form method="POST" action="{{ route('admin.services.publish',$service) }}" data-confirm="Publier ce service ?">@csrf<button class="button button--primary" type="submit">Publier</button></form>
                    <form method="POST" action="{{ route('admin.services.archive',$service) }}" data-confirm="Archiver ce service ?">@csrf<button class="button button--secondary" type="submit">Archiver</button></form>
                @endif
            @endcan
        </div>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Qualité</span><h3>Contenu & compétences</h3></div></div>
        <p>{{ $service->description }}</p>
        <div class="admin-chip-list">@forelse($service->skills as $skill)<span class="admin-chip">{{ $skill->name }}{{ $skill->status->value !== 'active' ? ' · '.$skill->status->value : '' }}</span>@empty<span class="admin-muted">Aucune compétence.</span>@endforelse</div>
        <div class="admin-image-grid">@foreach($service->images as $image)<div><span>{{ $image->is_cover ? 'Couverture' : 'Image' }}</span><small>{{ $image->alt_text ?: $image->path }}</small></div>@endforeach</div>
    </section>
</div>

@can('moderate', $service)
    @php
        $moderationActions = match($service->moderation_status->value) {
            'clear' => ['start_review' => 'Mettre en examen', 'request_changes' => 'Demander des corrections', 'hide' => 'Masquer'],
            'under_review' => ['approve' => 'Approuver', 'request_changes' => 'Demander des corrections', 'hide' => 'Masquer', 'reject' => 'Rejeter'],
            'changes_requested' => ['approve' => 'Valider les corrections', 'hide' => 'Masquer', 'reject' => 'Rejeter'],
            'hidden', 'rejected' => ['restore' => 'Restaurer / autoriser une nouvelle publication'],
            default => [],
        };
    @endphp
    @if($moderationActions !== [])
        <section class="admin-card">
            <div class="admin-card__header"><div><span class="admin-card__eyebrow">Modération du service</span><h3>Décision administrative</h3></div><span class="admin-status admin-status--{{ $service->moderation_status->value }}">{{ $service->moderation_status->value }}</span></div>
            <p class="admin-muted">La modération est indépendante de la publication. Masquer un service ne détruit pas son statut de publication ni les demandes historiques.</p>
            <form method="POST" action="{{ route('admin.services.moderate', $service) }}" class="admin-form">
                @csrf
                <label class="admin-field"><span>Décision</span><select name="decision" required>@foreach($moderationActions as $value=>$label)<option value="{{ $value }}" @selected(old('decision') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="admin-field"><span>Motif (obligatoire)</span><textarea name="reason" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea><small>Ce motif sera conservé dans l’historique et présenté au professionnel.</small></label>
                <button class="button button--primary" type="submit">Enregistrer la décision</button>
            </form>
        </section>
    @endif
@endcan

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Traçabilité</span><h3>Historique de modération</h3></div><span class="admin-muted">{{ $service->moderationDecisions->count() }} décision(s)</span></div>
    @forelse($service->moderationDecisions as $decision)
        <article class="admin-list-row">
            <div><strong>{{ str_replace('_', ' ', ucfirst($decision->action)) }}</strong><small>{{ $decision->from_status }} → {{ $decision->to_status }} · {{ $decision->actor?->name ?? 'Compte supprimé' }} · {{ $decision->created_at->format('d/m/Y H:i') }}</small>@if($decision->reason)<p>{{ $decision->reason }}</p>@endif</div>
        </article>
    @empty
        <p class="admin-muted">Aucune décision de modération n’a encore été enregistrée.</p>
    @endforelse
</section>

<section class="admin-card admin-realizations">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Modération distincte</span><h3>Preuves de réalisations</h3></div><span class="admin-muted">{{ $service->realizations->where('status', 'pending')->count() }} en attente</span></div>
    <p class="admin-muted">Les fichiers restent privés. Les décisions sur les preuves ne remplacent pas les décisions de modération du service.</p>
    @forelse($service->realizations as $realization)
        <article class="admin-realization-card">
            <img src="{{ route('admin.service-realizations.file', $realization) }}" alt="{{ $realization->caption }}" loading="lazy">
            <div class="admin-realization-card__content">
                <div class="admin-realization-card__heading"><strong>{{ $realization->caption }}</strong><span class="admin-status admin-status--{{ $realization->status }}">{{ ['pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Refusée'][$realization->status] ?? $realization->status }}</span></div>
                <p>Participation déclarée : {{ $realization->participation_confirmed ? 'Oui' : 'Non' }}</p>
                @if($realization->completed_at)<p>Date déclarée : {{ $realization->completed_at->format('d/m/Y') }}</p>@endif
                <p>Soumise le {{ $realization->created_at->format('d/m/Y H:i') }}</p>
                @if($realization->reviewer)<p>Examinée par {{ $realization->reviewer->name }} le {{ $realization->reviewed_at?->format('d/m/Y H:i') }}</p>@endif
                @if($realization->review_note)<p class="admin-realization-card__note">Motif / note : {{ $realization->review_note }}</p>@endif
                @can('adminManage',$service)
                    <div class="admin-action-row">
                        <form method="POST" action="{{ route('admin.service-realizations.approve', $realization) }}">@csrf<label for="approve-note-{{ $realization->id }}">Note facultative</label><input id="approve-note-{{ $realization->id }}" name="review_note" maxlength="2000"><button class="button button--primary" type="submit">Approuver</button></form>
                        <form method="POST" action="{{ route('admin.service-realizations.reject', $realization) }}">@csrf<label for="reject-note-{{ $realization->id }}">Motif de refus</label><textarea id="reject-note-{{ $realization->id }}" name="review_note" minlength="10" maxlength="2000" required></textarea><button class="button button--secondary" type="submit">Refuser</button></form>
                    </div>
                @endcan
            </div>
        </article>
    @empty
        <p class="admin-muted">Aucune réalisation n’a été soumise pour ce service.</p>
    @endforelse
</section>
@endsection
