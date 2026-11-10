@extends('layouts.public')
@section('title', $page->seo_title ?: $page->title.' — PROXIWORK')
@section('meta_description', $page->meta_description ?: ($page->excerpt ?: 'Informations PROXIWORK.'))
@if(!$page->is_indexable)@push('head')<meta name="robots" content="noindex,nofollow">@endpush @endif
@if($page->canonical_url)@push('head')<link rel="canonical" href="{{ $page->canonical_url }}">@endpush @endif
@section('content')
<main id="main-content" class="page-container"><article><h1>{{ $page->title }}</h1>@if($page->excerpt)<p>{{ $page->excerpt }}</p>@endif<div class="content-prose">{!! nl2br(e($page->body)) !!}</div></article></main>
@endsection
