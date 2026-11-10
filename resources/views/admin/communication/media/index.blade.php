@extends('layouts.admin')
@section('title', 'Médiathèque')
@section('content')
<div class="admin-page"><h1>Médiathèque publique</h1><p>Seules les images JPEG, PNG et WebP de 5 Mo maximum sont acceptées. Les documents privés de vérification professionnelle restent dans leur espace séparé.</p>
<section class="admin-card"><form method="POST" enctype="multipart/form-data" action="{{ route('admin.communication.media.store') }}">@csrf<label>Image <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label><label>Texte alternatif <input name="alt_text" maxlength="255" required></label><button>Téléverser</button></form></section>
<section class="admin-card"><h2>Fichiers enregistrés</h2><div class="admin-table-container"><table class="admin-table"><thead><tr><th>Aperçu</th><th>Nom</th><th>Type</th><th>Taille</th><th>Chemin public</th></tr></thead><tbody>
@forelse($assets as $asset)<tr><td><img src="{{ asset('storage/'.$asset->path) }}" alt="{{ $asset->alt_text }}" width="90" loading="lazy"></td><td>{{ $asset->original_name }}</td><td>{{ $asset->mime_type }}</td><td>{{ number_format($asset->size_bytes / 1024, 1) }} Ko</td><td><code>{{ $asset->path }}</code></td></tr>@empty<tr><td colspan="5">Aucun média.</td></tr>@endforelse
</tbody></table></div>{{ $assets->links() }}</section></div>
@endsection
