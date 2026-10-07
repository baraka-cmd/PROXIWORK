@extends('layouts.client')
@section('title','Messages')
@section('content')
@push('head')@vite('resources/css/pages/client/messages.css')@endpush
<div class="client-messages-page"><header class="client-module__header"><div><p class="client-module__eyebrow">MESSAGERIE</p><h1>Messages</h1><p>Échangez avec les professionnels avec lesquels vous êtes en relation.</p></div></header>
<div class="conversation-list client-panel">
@if($conversations->isEmpty())<div class="message-empty"><i class="fa-regular fa-comments" aria-hidden="true"></i><h2>Aucune conversation</h2><p>Vos conversations apparaîtront ici.</p></div>
@else
@foreach($conversations as $conversation)
<a class="conversation-row {{ $selected === $conversation->id ? 'is-selected' : '' }}" href="{{ route('client.messages.show',$conversation) }}">
<div class="conversation-avatar">{{ strtoupper(mb_substr(($conversation->professional?->user?->name ?? $conversation->client?->name ?? '?'),0,1)) }}</div>
<div class="conversation-content"><div class="conversation-top"><strong>{{ $conversation->professional?->user?->name ?? $conversation->client?->name }}</strong>@if($conversation->unread_count)<span class="unread-count">{{ $conversation->unread_count }}</span>@endif</div><p>{{ $conversation->lastMessage?->body ?? 'Nouvelle conversation' }}</p></div>
<time>{{ optional($conversation->updated_at)->diffForHumans() }}</time>
</a>
@endforeach
@endif
</div></div>
@endsection