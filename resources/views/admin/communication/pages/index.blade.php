@extends('layouts.admin')
@section('title', 'Pages publiques')
@section('content')
<div class="admin-page"><h1>Gestion des pages publiques</h1><p>Éditeur textuel sécurisé : le contenu est échappé côté Blade. Les routes dynamiques de recherche, services et professionnels restent gérées par leurs modèles métier.</p>
<section class="admin-card"><h2>Créer une page</h2><form method="POST" action="{{ route('admin.communication.pages.store') }}">@csrf
<label>Titre <input name="title" maxlength="180" required></label><label>Slug (minuscules et tirets) <input name="slug" maxlength="200"></label><label>Résumé <input name="excerpt" maxlength="500"></label><label>Contenu <textarea name="body" rows="8" maxlength="50000" required></textarea></label><label>Titre SEO <input name="seo_title" maxlength="180"></label><label>Description SEO <textarea name="meta_description" maxlength="320"></textarea></label><label>URL canonique <input name="canonical_url" type="url" maxlength="500"></label><label><input type="checkbox" name="is_indexable" value="1" checked> Indexable</label><button>Enregistrer en brouillon</button></form></section>
<section class="admin-card"><h2>Pages</h2><table class="admin-table"><thead><tr><th>Titre</th><th>Slug</th><th>État</th><th>Publication</th><th>Action</th></tr></thead><tbody>
@forelse($pages as $page)<tr><td>{{ $page->title }}</td><td>/pages/{{ $page->slug }}</td><td>{{ $page->status }}</td><td>{{ $page->published_at?->format('d/m/Y H:i') ?? '—' }}</td><td>@if($page->status !== 'published')<form method="POST" action="{{ route('admin.communication.pages.publish',$page) }}">@csrf<button>Publier</button></form>@else<a href="{{ route('public.pages.show',$page->slug) }}" target="_blank" rel="noopener">Voir</a>@endif</td></tr>@empty<tr><td colspan="5">Aucune page.</td></tr>@endforelse
</tbody></table>{{ $pages->links() }}</section></div>
@endsection
