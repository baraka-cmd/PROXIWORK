@extends('layouts.admin')
@section('title', 'Documents institutionnels')
@section('content')
<div class="admin-page"><h1>Documents institutionnels</h1><p>Chaque publication crée une version immuable. Une seconde personne doit approuver le brouillon avant sa publication.</p>
<section class="admin-card"><h2>Nouveau document</h2><form method="POST" action="{{ route('admin.communication.documents.store') }}">@csrf
<label>Titre <input name="title" maxlength="180" required></label><label>Slug <input name="slug" maxlength="180" required></label><label>Type <select name="document_type">@foreach(['terms','privacy','professional_rules','refund_policy','payment_information','other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></label><label>Contenu <textarea name="content" rows="8" maxlength="50000" required></textarea></label><label>Motif du changement <input name="change_reason" maxlength="500"></label><label><input type="checkbox" name="requires_acceptance" value="1"> Exiger une acceptation explicite</label><button>Créer version brouillon</button></form></section>
<section class="admin-card"><h2>Documents et versions</h2>
@forelse($documents as $document)<article><h3>{{ $document->title }} <small>({{ $document->status }})</small></h3><p>/legal/{{ $document->slug }} · Version courante {{ $document->current_version }}</p>
@foreach($document->versions as $version)<div><p>Version {{ $version->version }} — {{ $version->status }} — créée le {{ $version->created_at->format('d/m/Y H:i') }}</p>
@if($version->status === 'draft')<form method="POST" action="{{ route('admin.communication.documents.approve',$version) }}">@csrf<button>Approuver (autre administrateur)</button></form>@endif
@if($version->status === 'approved')<form method="POST" action="{{ route('admin.communication.documents.publish',$version) }}">@csrf<button>Publier cette version</button></form>@endif
</div>@endforeach</article><hr>@empty<p>Aucun document institutionnel.</p>@endforelse
{{ $documents->links() }}</section></div>
@endsection
