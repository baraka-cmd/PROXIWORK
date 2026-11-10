@extends('layouts.public')
@section('title', 'Annonces — PROXIWORK')
@section('meta_description', 'Actualités et annonces officielles de PROXIWORK.')
@section('content')
<main id="main-content" class="page-container"><h1>Annonces PROXIWORK</h1>
@forelse($announcements as $item)<article><p>{{ strtoupper($item->type) }} @if($item->is_pinned) · À la une @endif</p><h2>{{ $item->title }}</h2>@if($item->summary)<p>{{ $item->summary }}</p>@endif<p>{{ $item->body }}</p>@if($item->cta_url && $item->cta_label)<a href="{{ $item->cta_url }}">{{ $item->cta_label }}</a>@endif<small>Publié le {{ $item->published_at?->format('d/m/Y') }}</small></article><hr>@empty<p>Aucune annonce publiée pour le moment.</p>@endforelse
{{ $announcements->links() }}</main>
@endsection
