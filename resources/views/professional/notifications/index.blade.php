@extends('layouts.professional')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/professional/notifications.css', 'resources/js/pages/professional/notifications.js'])
    @endunless
@endpush

@section('page_title', 'Notifications')
@section('page_description', 'Retrouvez et organisez les événements importants liés à votre activité sur PROXIWORK.')

@section('page_actions')
    @if (! $archived && $unreadCount > 0)
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

    @if ($errors->any())
        <div class="feedback feedback--error" role="alert">
            <strong>Les modifications n'ont pas été enregistrées.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="notification-toolbar" aria-label="Résumé des notifications">
        <div>
            <strong>{{ $unreadCount }}</strong>
            <span>notification(s) non lue(s) dans la boîte de réception</span>
        </div>
        <nav class="notification-filters" aria-label="Emplacement des notifications">
            <a class="{{ ! $archived ? 'is-active' : '' }}" href="{{ route('professional.notifications', ['archived' => '0']) }}">Boîte de réception</a>
            <a class="{{ $archived ? 'is-active' : '' }}" href="{{ route('professional.notifications', ['archived' => '1']) }}">Archives</a>
        </nav>
    </section>

    <section class="notification-toolbar" aria-label="Recherche et filtres">
        <form method="GET" action="{{ route('professional.notifications') }}" class="notification-filters" role="search">
            <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Rechercher dans les notifications…" aria-label="Rechercher dans les notifications">
            <select name="category" aria-label="Catégorie">
                <option value="all" @selected($category === 'all')>Toutes les catégories</option>
                <option value="service_request" @selected($category === 'service_request')>Demandes</option>
                <option value="quotation" @selected($category === 'quotation')>Devis</option>
                <option value="order" @selected($category === 'order')>Commandes</option>
                <option value="payment" @selected($category === 'payment')>Paiements</option>
                <option value="message" @selected($category === 'message')>Messages</option>
                <option value="review" @selected($category === 'review')>Avis</option>
                <option value="account" @selected($category === 'account')>Compte et sécurité</option>
            </select>
            <select name="status" aria-label="État de lecture">
                <option value="all" @selected($status === 'all')>Toutes</option>
                <option value="unread" @selected($status === 'unread')>Non lues</option>
                <option value="read" @selected($status === 'read')>Lues</option>
            </select>
            <input type="hidden" name="archived" value="{{ $archived ? '1' : '0' }}">
            <button class="button button--primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrer</button>
            <a class="button button--secondary" href="{{ route('professional.notifications', ['archived' => $archived ? '1' : '0']) }}">Réinitialiser</a>
        </form>
    </section>

    @if ($notifications->isEmpty())
        <section class="notification-empty" aria-live="polite">
            <i class="fa-regular {{ $archived ? 'fa-folder-open' : 'fa-bell-slash' }}" aria-hidden="true"></i>
            <h3>{{ $archived ? 'Aucune notification archivée' : 'Aucune notification à afficher' }}</h3>
            <p>Les événements pertinents de votre activité apparaîtront ici. Vous pouvez modifier les filtres pour élargir la recherche.</p>
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
                    $priority = (string) ($data['priority'] ?? 'normal');
                @endphp

                <article class="notification-card {{ $notification->read_at ? 'notification-card--read' : 'notification-card--unread' }}">
                    <div class="notification-card__icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></div>
                    <div class="notification-card__body">
                        <div class="notification-card__heading">
                            <h3>{{ is_string($data['title'] ?? null) ? $data['title'] : 'Notification PROXIWORK' }}</h3>
                            <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->format('d/m/Y H:i') }}</time>
                        </div>
                        <p>{{ is_string($data['message'] ?? null) ? $data['message'] : '' }}</p>

                        @if ($priority === 'critical')
                            <p><strong>Attention : événement de sécurité ou opérationnel important.</strong></p>
                        @endif

                        @if ($notification->safe_url)
                            <a class="button button--small button--secondary" href="{{ $notification->safe_url }}">Consulter l'élément concerné</a>
                        @endif

                        <div class="notification-card__meta">
                            <span class="notification-type">{{ match ($action) {
                                'service_request' => 'Demande',
                                'quotation' => 'Devis',
                                'order' => 'Commande',
                                'payment' => 'Paiement',
                                'message' => 'Message',
                                'review' => 'Avis',
                                'account' => 'Compte et sécurité',
                                default => 'Plateforme',
                            } }}</span>

                            @if ($notification->read_at)
                                <span class="notification-read">Lue</span>
                            @else
                                <span class="notification-unread-dot">Non lue</span>
                            @endif

                            @if (! $archived && ! $notification->read_at)
                                <form method="POST" action="{{ route('professional.notifications.read', $notification->id) }}">
                                    @csrf
                                    <button class="button button--small button--secondary" type="submit">Marquer comme lue</button>
                                </form>
                            @endif

                            @if ($archived)
                                <form method="POST" action="{{ route('professional.notifications.restore', $notification->id) }}">
                                    @csrf
                                    <button class="button button--small button--secondary" type="submit">Restaurer</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('professional.notifications.archive', $notification->id) }}">
                                    @csrf
                                    <button class="button button--small button--secondary" type="submit">Archiver</button>
                                </form>
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

    <details class="notification-toolbar">
        <summary><strong>Préférences de notification externe</strong></summary>
        <p>Le centre de notifications PROXIWORK reste conservé dans l'application. Ces choix concernent les canaux externes configurés par la plateforme ; activer SMS ou push ne garantit pas leur envoi si le fournisseur n'est pas intégré.</p>
        <form method="POST" action="{{ route('professional.notifications.preferences.update') }}" class="notification-filters">
            @csrf
            @method('PATCH')
            <label><input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $preferences->email_enabled))> E-mail</label>
            <label><input type="checkbox" name="sms_enabled" value="1" @checked(old('sms_enabled', $preferences->sms_enabled))> SMS (si configuré)</label>
            <label><input type="checkbox" name="push_enabled" value="1" @checked(old('push_enabled', $preferences->push_enabled))> Push (si configuré)</label>
            <button class="button button--primary" type="submit">Enregistrer les préférences</button>
        </form>
    </details>
@endsection
