@extends('layouts.public')
@section('title','Annonces — PROXIWORK')
@section('meta_description','Informations et annonces officielles de PROXIWORK.')
@section('content')
<main class="page-container" style="max-width:960px;padding-top:3rem;padding-bottom:4rem"><h1>Annonces PROXIWORK</h1>@forelse($announcements as $item)<article class="admin-card" style="margin-top:1.25rem"><p class="home-section-heading__eyebrow">{{ $item->category }} · {{ $item->published_at?->format('d/m/Y') }}</p><h2>{{ $item->title }}</h2>@if($item->excerpt)<p>{{ $item->excerpt }}</p>@endif<div>{!! nl2br(e($item->body)) !!}</div></article>@empty<p>Aucune annonce actuellement.</p>@endforelse{{ $announcements->links() }}</main>
@endsection