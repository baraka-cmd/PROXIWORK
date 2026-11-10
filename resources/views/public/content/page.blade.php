@extends('layouts.public')
@section('title', $page->seo_title ?: $page->title.' — PROXIWORK')
@section('meta_description', $page->meta_description ?: ($page->excerpt ?: 'Informations PROXIWORK.'))
@if(!$page->is_indexable)@push('head')<meta name="robots" content="noindex,nofollow">@endpush @endif
@if($page->canonical_url)@push('head')<link rel="canonical" href="{{ $page->canonical_url }}">@endpush @endif
@push('head')<meta property="og:type" content="article"><meta property="og:title" content="{{ $page->seo_title ?: $page->title }}"><meta property="og:description" content="{{ $page->meta_description ?: ($page->excerpt ?: 'Informations PROXIWORK.') }}">@if($page->og_image_path)<meta property="og:image" content="{{ asset('storage/'.$page->og_image_path) }}">@endif @endpush
@section('content')
<main id="main-content" class="page-container"><article><h1>{{ $page->title }}</h1>@if($page->excerpt)<p>{{ $page->excerpt }}</p>@endif<div class="content-prose">{!! nl2br(e($page->body)) !!}</div></article></main>
@endsection
