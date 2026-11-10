@extends('layouts.public')
@section('title', $document->title.' — PROXIWORK')
@section('meta_description', 'Document institutionnel officiel de PROXIWORK.')
@section('content')
<main id="main-content" class="page-container"><article><h1>{{ $document->title }}</h1><p>Version {{ $version->version }} · En vigueur depuis {{ $version->effective_at?->format('d/m/Y') }}</p><div>{!! nl2br(e($version->content)) !!}</div>
@if($document->requires_acceptance && auth()->check())<form method="POST" action="{{ route('public.legal.accept',$document->slug) }}">@csrf<button>J’accepte cette version</button></form>@elseif($document->requires_acceptance)<p>Connectez-vous pour enregistrer votre acceptation de ce document.</p>@endif
</article></main>
@endsection
