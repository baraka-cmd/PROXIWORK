@extends('layouts.admin')
@section('title', 'Pages publiques')
@section('content')
<div class="admin-page"><h1>Gestion des pages publiques</h1><p>Éditeur textuel sécurisé : le contenu est échappé côté Blade. Les routes dynamiques de recherche, services et professionnels restent gérées par leurs modèles métier.</p>
<section class="admin-card"><h2>Créer une page</h2><form method="POST" action="{{ route('admin.communication.pages.store') }}">@csrf
<label>Titre <input name="title" maxlength="180" required></label><label>Slug (minuscules et tirets) <input name="slug" maxlength="200"></label><label>Résumé <input name="excerpt" maxlength="500"></label><label>Contenu <textarea name="body" rows="8" maxlength="50000" required></textarea></label><label>Titre SEO <input name="seo_title" maxlength="180"></label><label>Description SEO <textarea name="meta_description" maxlength="320"></textarea></label><label>Image Open Graph <select name="og_image_path"><option value="">Aucune</option>@foreach($mediaAssets as $asset)<option value="{{ $asset->path }}">{{ $asset->alt_text ?: $asset->path }}</option>@endforeach</select></label><label>URL canonique <input name="canonical_url" type="url" maxlength="500"></label><label><input type="checkbox" name="is_indexable" value="1" checked> Indexable</label><button>Enregistrer en brouillon</button></form></section>
<section class="admin-card"><h2>Pages</h2><table class="admin-table"><thead><tr><th>Titre</th><th>Slug</th><th>État</th><th>Publication</th><th>Action</th></tr></thead><tbody>
@forelse($pages as $page)<tr><td>{{ $page->title }}</td><td>/pages/{{ $page->slug }}</td><td>{{ $page->status }}</td><td>{{ $page->published_at?->format('d/m/Y H:i') ?? '—' }}</td><td>@if($page->draft_versions_count > 0)@if(auth()->user()?->hasPermissionTo('cms_pages.publish'))<form method="POST" action="{{ route('admin.communication.pages.publish',$page) }}">@csrf<button>Publier la dernière version</button></form>@endif@endif
@if($page->status === 'published')<a href="{{ route('public.pages.show',$page->slug) }}" target="_blank" rel="noopener">Voir la version en ligne</a>@endif
<details><summary>Préparer une nouvelle version</summary><form method="POST" action="{{ route('admin.communication.pages.update',$page) }}">@csrf @method('PUT')
<label>Titre <input name="title" value="{{ $page->title }}" maxlength="180" required></label>
<label>Slug <input name="slug" value="{{ $page->slug }}" maxlength="200"></label>
<label>Résumé <input name="excerpt" value="{{ $page->excerpt }}" maxlength="500"></label>
<label>Contenu <textarea name="body" rows="5" maxlength="50000" required>{{ $page->body }}</textarea></label>
<label>Titre SEO <input name="seo_title" value="{{ $page->seo_title }}" maxlength="180"></label>
<label>Description SEO <textarea name="meta_description" maxlength="320">{{ $page->meta_description }}</textarea></label>
<label>Image Open Graph <select name="og_image_path"><option value="">Aucune</option>@foreach($mediaAssets as $asset)<option value="{{ $asset->path }}" @selected($asset->path === $page->og_image_path)>{{ $asset->alt_text ?: $asset->path }}</option>@endforeach</select></label><label>URL canonique <input name="canonical_url" value="{{ $page->canonical_url }}" type="url" maxlength="500"></label>
<label><input type="checkbox" name="is_indexable" value="1" @checked($page->is_indexable)> Indexable</label><button>Enregistrer le brouillon de révision</button></form></details></td></tr>@empty<tr><td colspan="5">Aucune page.</td></tr>@endforelse
</tbody></table>{{ $pages->links() }}</section></div>
@endsection
