@extends('layouts.client')
@section('title','Conversation')
@section('content')
@push('head')@vite('resources/css/pages/client/messages.css')@endpush
<div class="client-messages-page">
<header class="client-module__header"><div><p class="client-module__eyebrow">MESSAGERIE</p><h1>{{ $conversation->professional?->user?->name ?? $conversation->client?->name }}</h1><p>{{ $conversation->professional?->professional_title ?? 'Conversation' }}</p></div><form method="POST" action="{{ route('client.messages.read',$conversation) }}">@csrf<button class="btn btn-secondary" type="submit">Marquer comme lu</button></form></header>
<section class="client-panel message-context"><span>Statut : {{ $conversation->status->value }}</span>@if($conversation->service_request_id)<span>Demande #{{ $conversation->service_request_id }}</span>@endif@if($conversation->order_id)<span>Commande #{{ $conversation->order_id }}</span>@endif</section>
<section class="client-panel message-thread" aria-live="polite">
@forelse($messages->reverse() as $message)
<div class="message-bubble {{ $message->sender_id === auth()->id() ? 'is-mine' : '' }}"><strong>{{ $message->sender?->name }}</strong><p>{{ $message->body }}</p><time>{{ optional($message->created_at)->diffForHumans() }}</time></div>
@empty<div class="message-empty"><i class="fa-regular fa-comments"></i><p>Aucun message.</p></div>@endforelse
</section>
<section class="client-panel message-composer"><form method="POST" action="{{ route('client.messages.store',$conversation) }}">@csrf<textarea name="body" rows="3" maxlength="5000" required placeholder="Écrire un message…">{{ old('body') }}</textarea><button class="btn btn-primary" type="submit">Envoyer</button></form></section>
</div>
@endsection