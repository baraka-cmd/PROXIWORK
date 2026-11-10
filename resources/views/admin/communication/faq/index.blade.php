@extends('layouts.admin')
@section('title', 'FAQ')
@section('content')
<div class="admin-page"><h1>FAQ et aide</h1><p>Les réponses sont stockées en texte simple ; elles ne peuvent pas injecter de HTML ou de scripts dans les pages publiques.</p>
<section class="admin-card"><h2>Ajouter une question</h2><form method="POST" action="{{ route('admin.communication.faq.store') }}">@csrf
<label>Catégorie <select name="faq_category_id">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label><label>Question <input name="question" maxlength="255" required></label><label>Slug facultatif <input name="slug" maxlength="200"></label><label>Réponse <textarea name="answer" rows="6" maxlength="30000" required></textarea></label><label>Audience <select name="audience"><option value="all">Tous</option><option value="client">Clients</option><option value="professional">Professionnels</option></select></label><label>Langue <select name="locale"><option value="fr">Français</option><option value="en">English</option></select></label><label>Ordre <input type="number" name="sort_order" min="0" value="0"></label><button>Créer le brouillon</button></form></section>
<section class="admin-card"><h2>Questions existantes</h2><table class="admin-table"><thead><tr><th>Question</th><th>Catégorie</th><th>Audience</th><th>État</th><th>Action</th></tr></thead><tbody>
@forelse($articles as $article)<tr><td>{{ $article->question }}</td><td>{{ $article->category?->name }}</td><td>{{ $article->audience }}</td><td>{{ $article->status }}</td><td>@if($article->status !== 'published')<form method="POST" action="{{ route('admin.communication.faq.publish',$article) }}">@csrf<button>Publier</button></form>@endif</td></tr>@empty<tr><td colspan="5">Aucune question.</td></tr>@endforelse
</tbody></table>{{ $articles->links() }}</section></div>
@endsection
