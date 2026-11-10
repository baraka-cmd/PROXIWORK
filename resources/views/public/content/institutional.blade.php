@extends('layouts.public')
@section('title',$content->title.' — PROXIWORK')
@section('meta_description','Information institutionnelle PROXIWORK.')
@section('content')
<main class="page-container" style="max-width:900px;padding-top:3rem;padding-bottom:4rem"><p class="home-section-heading__eyebrow">INFORMATION OFFICIELLE</p><h1>{{ $content->title }}</h1><div>{!! nl2br(e($content->body)) !!}</div>@if($content->effective_at)<small>En vigueur depuis le {{ $content->effective_at->format('d/m/Y') }}</small>@endif</main>
@endsection