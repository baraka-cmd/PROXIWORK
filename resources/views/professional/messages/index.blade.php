@extends('layouts.professional')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/professional/reviews-messages.css', 'resources/js/pages/professional/reviews-messages.js'])
    @endunless
@endpush

@section('page_title', 'Messages')
@section('page_description', 'Échangez avec les clients qui vous contactent au sujet de leurs demandes et commandes.')

@section('content')
    @if (session('success'))
        <div class="feedback feedback--success" role="status">{{ session('success') }}</div>
    @endif

    @if ($conversations->isEmpty())
        <section class="data-card data-card--empty">
            <i class="fa-regular fa-comments" aria-hidden="true"></i>
            <h3>Aucune conversation</h3>
            <p>Les conversations démarrées par vos clients apparaîtront ici.</p>
        </section>
    @else
        <div class="conversation-list">
            @foreach ($conversations as $conversation)
                <a class="conversation-card" href="{{ route('professional.messages.show', $conversation) }}">
                    <span class="conversation-card__avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                    <span class="conversation-card__body">
                        <strong>{{ $conversation->client?->name ?? 'Client' }}</strong>
                        <small>
                            {{ $conversation->lastMessage?->body ? \Illuminate\Support\Str::limit($conversation->lastMessage->body, 90) : 'Aucun message' }}
                        </small>
                        @if ($conversation->unread_count > 0)
                            <span class="unread-badge">{{ $conversation->unread_count }} non lu(s)</span>
                        @endif
                    </span>
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </a>
            @endforeach
        </div>

        <div class="pagination-wrap">
            {{ $conversations->links() }}
        </div>
    @endif
@endsection
