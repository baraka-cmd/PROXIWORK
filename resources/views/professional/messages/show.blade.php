@extends('layouts.professional')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/professional/reviews-messages.css', 'resources/js/pages/professional/reviews-messages.js'])
    @endunless
@endpush

@section('page_title', 'Conversation')
@section('page_description', 'Échange sécurisé avec votre client.')

@section('page_actions')
    <a class="button button--secondary" href="{{ route('professional.messages') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour
    </a>
@endsection

@section('content')
    <section class="conversation-panel">
        <header class="conversation-panel__header">
            <div>
                <span class="eyebrow">CLIENT</span>
                <h3>{{ $conversation->client?->name ?? 'Client' }}</h3>
                @if ($conversation->order)
                    <small>Commande #{{ $conversation->order->id }}</small>
                @elseif ($conversation->serviceRequest)
                    <small>Demande #{{ $conversation->serviceRequest->id }}</small>
                @endif
            </div>
            <span class="conversation-status">{{ $conversation->status->value === 'open' ? 'Ouverte' : 'Fermée' }}</span>
        </header>

        <div class="message-thread" data-message-thread>
            @forelse ($messages as $message)
                <article class="message-bubble {{ $message->sender_id === auth()->id() ? 'message-bubble--mine' : 'message-bubble--theirs' }}">
                    <div class="message-bubble__meta">
                        <strong>{{ $message->sender?->name ?? 'Utilisateur' }}</strong>
                        <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('d/m/Y H:i') }}</time>
                    </div>
                    <p>{{ $message->body }}</p>
                </article>
            @empty
                <p class="empty-inline">Aucun message dans cette conversation.</p>
            @endforelse
        </div>

        @if ($conversation->status->value === 'open')
            <form method="POST" action="{{ route('professional.messages.store', $conversation) }}" class="message-composer" data-message-form>
                @csrf
                <label for="message-body">Votre message</label>
                <textarea id="message-body" name="body" maxlength="5000" rows="4" required placeholder="Écrivez votre message...">{{ old('body') }}</textarea>
                @error('body')
                    <span class="form-error">{{ $message }}</span>
                @enderror
                <div class="message-composer__footer">
                    <small>Maximum 5 000 caractères</small>
                    <button class="button button--primary" type="submit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Envoyer
                    </button>
                </div>
            </form>
        @else
            <div class="notice-box">Cette conversation est fermée et n'accepte plus de nouveaux messages.</div>
        @endif
    </section>
@endsection
