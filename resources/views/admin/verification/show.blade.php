@extends('layouts.admin')

@section('page_eyebrow','ADMINISTRATION / VÉRIFICATION')
@section('page_title',$professional->user->name ?? 'Dossier professionnel')
@section('page_description','Examen du dossier, des justificatifs privés et des décisions précédentes.')
@section('page_actions')<a class="button button--secondary" href="{{ route('admin.verification.index') }}"><i class="fa-solid fa-arrow-left"></i> Centre de vérification</a>@endsection
@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/permissions-professionals.css', 'resources/js/pages/admin/permissions-professionals.js'])
    @endunless
@endpush

@section('content')
@if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>@endif
@php($status = $professional->verification_status?->value)
<div class="admin-detail-grid">
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">Identité du compte</span><h3>{{ $professional->user->name }}</h3></div><span class="admin-status admin-status--{{ $professional->user->account_status?->value }}">{{ $professional->user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</span></div>
        <dl class="admin-detail-list">
            <div><dt>E-mail</dt><dd>{{ $professional->user->email }}</dd></div>
            <div><dt>E-mail confirmé</dt><dd>{{ $professional->user->email_verified_at ? 'Oui' : 'Non' }}</dd></div>
            <div><dt>Titre professionnel</dt><dd>{{ $professional->professional_title ?: 'Non renseigné' }}</dd></div>
            <div><dt>Entreprise</dt><dd>{{ $professional->business_name ?: 'Non renseignée' }}</dd></div>
            <div><dt>Localisation</dt><dd>{{ collect([$professional->commune,$professional->city,$professional->province])->filter()->implode(', ') ?: 'Non renseignée' }}</dd></div>
            <div><dt>Expérience</dt><dd>{{ $professional->years_experience !== null ? $professional->years_experience.' an(s)' : 'Non renseignée' }}</dd></div>
        </dl>
    </section>
    <section class="admin-card">
        <div class="admin-card__header"><div><span class="admin-card__eyebrow">État de confiance</span><h3>État de vérification</h3></div></div>
        <div class="admin-stat-grid">
            <div class="admin-stat"><span>Vérification</span><strong>{{ ['pending'=>'En attente','under_review'=>'En revue','additional_documents_requested'=>'Documents demandés','verified'=>'Vérifié','rejected'=>'Rejeté'][$status] ?? '—' }}</strong></div>
            <div class="admin-stat"><span>Justificatifs</span><strong>{{ $professional->documents_count }}</strong></div>
            <div class="admin-stat"><span>Compte</span><strong>{{ $professional->user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</strong></div>
        </div>
        <div class="admin-note"><i class="fa-solid fa-circle-info"></i><span>La vérification, l’activation du compte, l’état du profil et sa visibilité publique sont des états indépendants. Valider ce dossier ne réactive pas un compte suspendu.</span></div>
    </section>
</div>

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Informations déclarées</span><h3>Profil et compétences</h3></div></div>
    <p>{{ $professional->description ?: 'Aucune description fournie.' }}</p>
    <div class="admin-chip-list admin-chip-list--large">@forelse($professional->skills as $skill)<span class="admin-chip">{{ $skill->name }} @if($skill->pivot?->years_experience) · {{ $skill->pivot->years_experience }} an(s) @endif</span>@empty<span class="admin-muted">Aucune compétence renseignée.</span>@endforelse</div>
</section>

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Justificatifs privés</span><h3>Documents transmis</h3></div></div>
    @can('viewVerificationDocuments',$professional)
        <div class="verification-documents">
        @forelse($professional->documents as $document)
            <article class="verification-document">
                <div class="verification-document__heading">
                    <div><strong>{{ $documentTypes[$document->document_type] ?? 'Autre justificatif' }}</strong><small>{{ $document->original_name }} · {{ number_format($document->size / 1024, 0, ',', ' ') }} Ko</small></div>
                    <span class="admin-status admin-status--{{ $document->status }}">{{ ['pending'=>'À examiner','approved'=>'Approuvé','rejected'=>'Rejeté'][$document->status] ?? $document->status }}</span>
                </div>
                <div class="admin-action-row">
                    <a class="button button--secondary" href="{{ route('admin.professionals.documents.download',$document) }}"><i class="fa-solid fa-lock"></i> Télécharger le justificatif privé</a>
                    @if($document->status === 'pending')
                        @can('reviewDocument',$professional)
                            <form method="POST" action="{{ route('admin.verification.documents.approve',[$professional,$document]) }}">@csrf<button class="button button--primary" type="submit">Approuver</button></form>
                            <form method="POST" action="{{ route('admin.verification.documents.reject',[$professional,$document]) }}" class="verification-reject-form">
                                @csrf<label class="admin-field"><span>Motif obligatoire si rejet</span><input type="text" name="note" required minlength="5" maxlength="2000" placeholder="Expliquer le problème constaté"></label><button class="button button--secondary" type="submit">Rejeter ce document</button>
                            </form>
                        @endcan
                    @endif
                </div>
                @if($document->review_note)<p class="admin-note"><span>Dernière note : {{ $document->review_note }}</span></p>@endif
                @can('reviewDocument',$professional)
                    @if($document->reviews->isNotEmpty())
                        <details class="verification-history"><summary>Historique des décisions du document ({{ $document->reviews->count() }})</summary>
                            @foreach($document->reviews as $review)<p><strong>{{ $review->decision === 'approved' ? 'Approuvé' : 'Rejeté' }}</strong> · {{ $review->admin?->name ?? 'Administration' }} · {{ $review->created_at?->format('d/m/Y H:i') }} @if($review->note) — {{ $review->note }} @endif</p>@endforeach
                        </details>
                    @endif
                @endcan
            </article>
        @empty
            <div class="admin-empty"><strong>Aucun justificatif transmis</strong><span>Le professionnel doit transmettre les pièces nécessaires avant toute validation.</span></div>
        @endforelse
        </div>
    @else
        <p class="admin-muted">Votre rôle ne permet pas de consulter les justificatifs privés.</p>
    @endcan
</section>

<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Décision administrative</span><h3>Actions de vérification</h3></div></div>
    <div class="admin-action-row">
        @if(in_array($status,['pending','additional_documents_requested'],true))
            @can('review',$professional)
                <form method="POST" action="{{ route('admin.professionals.verification.start',$professional) }}">@csrf<button class="button button--primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> {{ $status === 'pending' ? 'Démarrer la revue' : 'Reprendre la revue après réception' }}</button></form>
            @endcan
        @elseif($status === 'under_review')
            @can('verify',$professional)<form method="POST" action="{{ route('admin.professionals.verification.verify',$professional) }}" data-confirm="Confirmer la vérification finale ? Tous les justificatifs doivent être approuvés.">@csrf<button class="button button--primary" type="submit"><i class="fa-solid fa-circle-check"></i> Approuver le professionnel</button></form>@endcan
            @can('requestDocuments',$professional)<button class="button button--secondary" type="button" data-open-note="request-documents-modal">Demander des documents complémentaires</button>@endcan
            @can('reject',$professional)<button class="button button--secondary" type="button" data-open-note="reject-modal">Rejeter définitivement le dossier</button>@endcan
        @else
            <span class="admin-muted">Aucune autre décision n’est autorisée pour cet état. Un rejet final est terminal dans le workflow actuel.</span>
        @endif
    </div>
    @can('requestDocuments',$professional)
        @if($status === 'under_review')
            <div id="request-documents-modal" class="admin-modal" hidden><div class="admin-modal__panel" role="dialog" aria-modal="true" aria-labelledby="request-documents-title"><h3 id="request-documents-title">Demander des documents complémentaires</h3><p>Expliquez précisément les pièces manquantes ou à remplacer. Le professionnel recevra une notification.</p><form method="POST" action="{{ route('admin.verification.request-documents',$professional) }}">@csrf<label class="admin-field"><span>Instructions destinées au professionnel</span><textarea name="instructions" required minlength="10" maxlength="2000" rows="5"></textarea></label><div class="admin-action-row"><button class="button button--secondary" type="button" data-close-modal>Annuler</button><button class="button button--primary" type="submit">Envoyer la demande</button></div></form></div></div>
        @endif
    @endcan
    @if($status === 'under_review')
        @can('reject',$professional)
            <div id="reject-modal" class="admin-modal" hidden><div class="admin-modal__panel" role="dialog" aria-modal="true" aria-labelledby="reject-title"><h3 id="reject-title">Rejet final du dossier</h3><form method="POST" action="{{ route('admin.professionals.verification.reject',$professional) }}">@csrf<label class="admin-field"><span>Motif</span><select name="reason_code" required><option value="DOCUMENT_INVALID">Document invalide</option><option value="PROFILE_INCOMPLETE">Profil incomplet</option><option value="IDENTITY_MISMATCH">Identité non concordante</option><option value="OTHER">Autre</option></select></label><label class="admin-field"><span>Explication communiquée au professionnel</span><textarea name="note" required minlength="5" maxlength="2000" rows="5"></textarea></label><div class="admin-action-row"><button class="button button--secondary" type="button" data-close-modal>Annuler</button><button class="button button--primary" type="submit">Confirmer le rejet final</button></div></form></div></div>
        @endcan
    @endif
</section>

@can('viewVerificationHistory',$professional)
<section class="admin-card">
    <div class="admin-card__header"><div><span class="admin-card__eyebrow">Traçabilité</span><h3>Historique du dossier</h3></div></div>
    <div class="admin-timeline">
        @forelse($professional->verificationReviews as $review)
            <article class="admin-timeline__item"><div class="admin-timeline__dot"></div><div><strong>{{ $review->from_status?->value ?? '—' }} → {{ $review->to_status?->value ?? '—' }}</strong><p>{{ $review->admin?->name ?? 'Administration' }} · {{ $review->created_at?->format('d/m/Y H:i') }}</p>@if($review->reason_code)<p>Code : {{ $review->reason_code }}</p>@endif @if($review->note)<p>{{ $review->note }}</p>@endif</div></article>
        @empty<p class="admin-muted">Aucune décision enregistrée.</p>@endforelse
    </div>
</section>
@endcan
@endsection
