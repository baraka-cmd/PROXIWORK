@extends('layouts.public')
@section('title', 'FAQ — PROXIWORK')
@section('meta_description', 'Réponses aux questions fréquentes sur PROXIWORK.')
@section('content')
<main id="main-content" class="page-container"><h1>Questions fréquentes</h1><form method="GET" action="{{ route('public.faq') }}"><label for="faq-search">Rechercher une question</label><input id="faq-search" type="search" name="q" value="{{ $search }}" maxlength="120"><button>Rechercher</button></form>
@forelse($articles as $article)<article><h2>{{ $article->question }}</h2><p><strong>{{ $article->category?->name }}</strong></p><div>{!! nl2br(e($article->answer)) !!}</div></article><hr>@empty<p>Aucune question trouvée.</p>@endforelse
{{ $articles->links() }}</main>
@endsection
