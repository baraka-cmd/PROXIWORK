@extends('layouts.professional')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/professional/notifications.css', 'resources/js/pages/professional/notifications.js'])
    @endunless
@endpush

@section('page_title', 'Notifications')
@section('page_description', 'Retrouvez les événements importants liés à votre activité sur PROXIWORK.')

@section('page_actions')
    @if ($unreadCount > 0)
        <form method="POST" action="{{ route('professional.notifications.read-all') }}">
            @csrf
            <button class="button button--secondary" type="submit" data-notification-read-all>
                <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                Tout marquer comme lu
            </button>
        </form>
    @endif
@endsection

@section('content')
    @if (session('success'))
        <div class="feedback feedback--success" role="status">{{ session('success') }}</div>
    @endif

    <section class="notification-toolbar" aria-label="Filtres des notifications">
        <div>
            <strong>{{ $unreadCount }}</strong>
            <span>notification(s) non lue(s)</span>
        </div>

        <nav class="notification-filters" aria-label="Filtrer les notifications">
            <a class="{{ $status === '' ? 'is-active' : '' }}" href="{{ route('professional.notifications') }}">Toutes</a>
            <a class="{{ $status === 'unread' ? 'is-active' : '' }}" href="{{ route('professional.notifications', ['status' => 'unread']) }}">Non lues</a>
            <a class="{{ $status === 'read' ? 'is-active' : '' }}" href="{{ route('professional.notifications', ['status' => 'read']) }}">Lues</a>
        </nav>
    </section>

    @if ($notifications->isEmpty())
        <section class="notification-empty" aria-live="polite">
            <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
            <h3>Aucune notification</h3>
            <p>Les événements importants de votre activité apparaîtront ici.</p>
        </section>
    @else
        <div class="notification-list">
            @foreach ($notifications as $notification)
                @php
                    $data = is_array($notification->data) ? $notification->data : [];
                    $action = (string) ($data['action'] ?? 'account');
                    $icon = match ($action) {
                        'message' => 'fa-comments',
                        'review' => 'fa-star',
                        'payment' => 'fa-credit-card',
                        'order' => 'fa-cart-shopping',
                        'quotation' => 'fa-file-invoice-dollar',
                        'service_request' => 'fa-file-lines',
                        default => 'fa-bell',
                    };
                @endphp

                <article class="notification-card {{ $notification->read_at ? 'notification-card--read' : 'notification-card--unread' }}">
                    <div class="notification-card__icon" aria-hidden="true">
                        <i class="fa-solid {{ $icon }}"></i>
                    </div>

                    <div class="notification-card__body">
                        <div class="notification-card__heading">
                            <h3>{{ $data['title'] ?? 'Notification' }}</h3>
                            <time datetime="{{ $notification->created_at?->toIso8601String() }}">
                                {{ $notification->created_at?->format('d/m/Y H:i') }}
                            </time>
                        </div>

                        <p>{{ $data['message'] ?? '' }}</p>

                        <div class="notification-card__meta">
                            <span class="notification-type">{{ $action }}</span>

                            @if (! $notification->read_at)
                                <span class="notification-unread-dot" aria-label="Non lue">Non lue</span>
                                <form method="POST" action="{{ route('professional.notifications.read', $notification->id) }}">
                                    @csrf
                                    <button class="button button--small button--secondary" type="submit">
                                        Marquer comme lue
                                    </button>
                                </form>
                            @else
                                <span class="notification-read">Lue</span>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <nav class="pagination-wrap" aria-label="Pagination des notifications">
            {{ $notifications->links() }}
        </nav>
    @endif
@endsection
