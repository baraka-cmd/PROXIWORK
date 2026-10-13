@extends('layouts.admin')
@section('page_eyebrow','PLATEFORME / SERVICE')
@section('page_title',$service->title)
@section('page_description',$service->short_description ?: 'Détail et modération du service.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.services.index') }}"><i class="fa-solid fa-arrow-left"></i> Services</a>@endsection
@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/services-requests.css', 'resources/js/pages/admin/services-requests.js'])
    @endunless
@endpush
@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="admin-detail-grid"><section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Service</span><h3>Informations principales</h3></div><span class="admin-status admin-status--{{ $service->status->value }}">{{ ['draft'=>'Brouillon','published'=>'Publié','unpublished'=>'Dépublié','archived'=>'Archivé'][$service->status->value] }}</span></div>
<dl class="admin-detail-list"><div><dt>Professionnel</dt><dd>{{ $service->professionalProfile->user->name ?? '—' }}</dd></div><div><dt>Catégorie</dt><dd>{{ $service->category->name ?? '—' }}</dd></div><div><dt>Tarification</dt><dd>{{ $service->pricing_type->value }} · {{ $service->currency }}</dd></div><div><dt>Prix</dt><dd>{{ $service->price ?? ($service->price_min.' — '.$service->price_max) ?? 'Sur devis' }}</dd></div><div><dt>Durée estimée</dt><dd>{{ $service->estimated_duration_minutes ? $service->estimated_duration_minutes.' min' : '—' }}</dd></div><div><dt>Demandes</dt><dd>{{ $service->service_requests_count }}</dd></div></dl>
<div class="admin-action-row">@can('adminManage',$service)@if($service->status->value==='published')<form method="POST" action="{{ route('admin.services.unpublish',$service) }}" data-confirm="Dépublier ce service ?">@csrf<button class="button button--secondary">Dépublier</button></form>@elseif(in_array($service->status->value,['draft','unpublished']))<form method="POST" action="{{ route('admin.services.publish',$service) }}" data-confirm="Publier ce service ?">@csrf<button class="button button--primary">Publier</button></form><form method="POST" action="{{ route('admin.services.archive',$service) }}" data-confirm="Archiver ce service ?">@csrf<button class="button button--secondary">Archiver</button></form>@endif @endcan</div></section>
<section class="admin-card"><div class="admin-card__header"><div><span class="admin-card__eyebrow">Qualité</span><h3>Contenu & compétences</h3></div></div><p>{{ $service->description }}</p><div class="admin-chip-list">@forelse($service->skills as $skill)<span class="admin-chip">{{ $skill->name }}</span>@empty<span class="admin-muted">Aucune compétence.</span>@endforelse</div><div class="admin-image-grid">@foreach($service->images as $image)<div><span>{{ $image->is_cover ? 'Couverture' : 'Image' }}</span><small>{{ $image->alt_text ?: $image->path }}</small></div>@endforeach</div></section></div>

<section class="admin-card admin-realizations">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Modération</span><h3>Preuves de réalisations</h3></div><span class="admin-muted">{{ $service->realizations->where('status', 'pending')->count() }} en attente</span></div>
    <p class="admin-muted">Les fichiers restent privés. Seules les réalisations approuvées peuvent être servies publiquement, et uniquement lorsque le service est lui-même visible.</p>
    @forelse($service->realizations as $realization)
        <article class="admin-realization-card">
            <img src="{{ route('admin.service-realizations.file', $realization) }}" alt="{{ $realization->caption }}" loading="lazy">
            <div class="admin-realization-card__content">
                <div class="admin-realization-card__heading">
                    <strong>{{ $realization->caption }}</strong>
                    <span class="admin-status admin-status--{{ $realization->status }}">{{ ['pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Refusée'][$realization->status] ?? $realization->status }}</span>
                </div>
                <p>Participation déclarée : {{ $realization->participation_confirmed ? 'Oui' : 'Non' }}</p>
                @if($realization->completed_at)<p>Date déclarée : {{ $realization->completed_at->format('d/m/Y') }}</p>@endif
                <p>Soumise le {{ $realization->created_at->format('d/m/Y H:i') }}</p>
                @if($realization->reviewer)<p>Examinée par {{ $realization->reviewer->name }} le {{ $realization->reviewed_at?->format('d/m/Y H:i') }}</p>@endif
                @if($realization->review_note)<p class="admin-realization-card__note">Motif / note : {{ $realization->review_note }}</p>@endif
                @can('adminManage', $service)
                    <div class="admin-action-row">
                        <form method="POST" action="{{ route('admin.service-realizations.approve', $realization) }}">
                            @csrf
                            <label for="approve-note-{{ $realization->id }}">Note facultative</label>
                            <input id="approve-note-{{ $realization->id }}" name="review_note" maxlength="2000">
                            <button class="button button--primary" type="submit">Approuver</button>
                        </form>
                        <form method="POST" action="{{ route('admin.service-realizations.reject', $realization) }}">
                            @csrf
                            <label for="reject-note-{{ $realization->id }}">Motif de refus</label>
                            <textarea id="reject-note-{{ $realization->id }}" name="review_note" minlength="10" maxlength="2000" required></textarea>
                            <button class="button button--secondary" type="submit">Refuser</button>
                        </form>
                    </div>
                @endcan
            </div>
        </article>
    @empty
        <p class="admin-muted">Aucune réalisation n’a été soumise pour ce service.</p>
    @endforelse
</section>
@endsection