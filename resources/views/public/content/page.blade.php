@extends('layouts.public')
@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description ?: ($page->excerpt ?: 'Informations institutionnelles PROXIWORK.'))
@section('content')
<main class="page-container" style="max-width:900px;padding-top:3rem;padding-bottom:4rem"><p class="home-section-heading__eyebrow">PROXIWORK</p><h1>{{ $page->title }}</h1>@if($page->excerpt)<p>{{ $page->excerpt }}</p>@endif<div class="public-rich-text">{!! nl2br(e($page->body)) !!}</div></main>
@endsection