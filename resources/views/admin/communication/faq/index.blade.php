@extends('layouts.admin')
@section('title', 'FAQ')
@section('content')
<div class="admin-page">
    <h1>FAQ et aide</h1>
    <p>Les réponses sont stockées en texte simple ; elles ne peuvent pas injecter de HTML ou de scripts dans les pages publiques.</p>

    <section class="admin-card">
        <h2>Créer une catégorie</h2>
        <form method="POST" action="{{ route('admin.communication.faq.categories.store') }}">
            @csrf
            <label>Nom <input name="name" maxlength="120" required></label>
            <label>Slug facultatif <input name="slug" maxlength="140"></label>
            <button>Créer la catégorie</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Ajouter une question</h2>
        <form method="POST" action="{{ route('admin.communication.faq.store') }}">
            @csrf
            <label>Catégorie
                <select name="faq_category_id">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Question <input name="question" maxlength="255" required></label>
            <label>Slug facultatif <input name="slug" maxlength="200"></label>
            <label>Réponse <textarea name="answer" rows="6" maxlength="30000" required></textarea></label>
            <label>Audience
                <select name="audience">
                    <option value="all">Tous</option>
                    <option value="client">Clients</option>
                    <option value="professional">Professionnels</option>
                </select>
            </label>
            <label>Langue
                <select name="locale">
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                </select>
            </label>
            <label>Ordre <input type="number" name="sort_order" min="0" value="0"></label>
            <button>Créer le brouillon</button>
        </form>
    </section>

    <section class="admin-card">
        <h2>Questions existantes</h2>
        <table class="admin-table">
            <thead><tr><th>Question</th><th>Catégorie</th><th>Audience</th><th>État</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($articles as $article)
                <tr>
                    <td>{{ $article->question }}</td>
                    <td>{{ $article->category?->name }}</td>
                    <td>{{ $article->audience }}</td>
                    <td>{{ $article->status }} · {{ $article->versions_count }} version(s)</td>
                    <td>
                        @if ($article->draft_versions_count > 0)
                            @if (auth()->user()?->hasPermissionTo('faq.publish'))
                                <form method="POST" action="{{ route('admin.communication.faq.publish', $article) }}">
                                    @csrf
                                    <button>Publier la dernière version</button>
                                </form>
                            @endif
                        @endif

                        <details>
                            <summary>Préparer une nouvelle version</summary>
                            <form method="POST" action="{{ route('admin.communication.faq.update', $article) }}">
                                @csrf
                                @method('PUT')
                                <label>Catégorie
                                    <select name="faq_category_id">
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected($category->id === $article->faq_category_id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Question <input name="question" value="{{ $article->question }}" maxlength="255" required></label>
                                <label>Slug <input name="slug" value="{{ $article->slug }}" maxlength="200"></label>
                                <label>Réponse <textarea name="answer" rows="4" maxlength="30000" required>{{ $article->answer }}</textarea></label>
                                <label>Audience
                                    <select name="audience">
                                        <option value="all" @selected($article->audience === 'all')>Tous</option>
                                        <option value="client" @selected($article->audience === 'client')>Clients</option>
                                        <option value="professional" @selected($article->audience === 'professional')>Professionnels</option>
                                    </select>
                                </label>
                                <label>Langue
                                    <select name="locale">
                                        <option value="fr" @selected($article->locale === 'fr')>Français</option>
                                        <option value="en" @selected($article->locale === 'en')>English</option>
                                    </select>
                                </label>
                                <label>Ordre <input type="number" name="sort_order" min="0" value="{{ $article->sort_order }}"></label>
                                <button>Enregistrer le brouillon de révision</button>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Aucune question.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $articles->links() }}
    </section>
</div>
@endsection
