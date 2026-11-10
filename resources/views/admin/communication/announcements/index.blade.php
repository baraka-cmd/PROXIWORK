@extends('layouts.admin')
@section('title', 'Annonces')
@section('content')
<div class="admin-page"><h1>Gestion des annonces</h1><p>Les annonces publiques sont distinctes des notifications transactionnelles et des campagnes privées.</p>
<section class="admin-card"><h2>Créer une annonce</h2><form method="POST" action="{{ route('admin.communication.announcements.store') }}">@csrf
<label>Titre <input name="title" maxlength="180" required></label><label>Résumé <input name="summary" maxlength="500"></label><label>Contenu <textarea name="body" rows="5" required maxlength="20000"></textarea></label>
<label>Type <select name="type"><option value="information">Information</option><option value="alert">Alerte</option><option value="official">Annonce officielle</option></select></label>
<label>Emplacement <select name="placement"><option value="announcements">Page annonces</option><option value="homepage">Accueil</option><option value="client">Espace client</option><option value="professional">Espace professionnel</option></select></label>
<label>Libellé du lien <input name="cta_label" maxlength="80"></label><label>URL interne (commence par /) <input name="cta_url" maxlength="500" placeholder="/faq"></label>
<label>Début <input type="datetime-local" name="starts_at"></label><label>Fin <input type="datetime-local" name="ends_at"></label><label><input type="checkbox" name="is_pinned" value="1"> Épingler</label><button type="submit">Créer le brouillon</button></form></section>
<section class="admin-card"><h2>Annonces existantes</h2><div class="admin-table-container"><table class="admin-table"><thead><tr><th>Titre</th><th>Emplacement</th><th>État</th><th>Période</th><th>Actions</th></tr></thead><tbody>
@forelse($announcements as $item)<tr><td>{{ $item->title }}</td><td>{{ $item->placement }}</td><td>{{ $item->status }}</td><td>{{ $item->starts_at?->format('d/m/Y H:i') ?? 'Immédiat' }} – {{ $item->ends_at?->format('d/m/Y H:i') ?? 'Sans fin' }}</td><td>@if(in_array($item->status,['draft','scheduled']))<form method="POST" action="{{ route('admin.communication.announcements.publish',$item) }}">@csrf<button>Publier / programmer</button></form>@endif @if($item->status !== 'archived')<form method="POST" action="{{ route('admin.communication.announcements.archive',$item) }}">@csrf<button>Archiver</button></form>@endif</td></tr>@empty<tr><td colspan="5">Aucune annonce.</td></tr>@endforelse
</tbody></table></div>{{ $announcements->links() }}</section></div>
@endsection
