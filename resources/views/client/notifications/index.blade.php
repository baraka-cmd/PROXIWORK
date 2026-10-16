@extends('layouts.client')

@section('title', 'Mes notifications')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/client/notifications.css')
    @endunless
@endpush

@section('content')
    <div class="client-notifications-page">
        <header class="client-module__header notification-page-header">
            <div>
                <p class="client-module__eyebrow">CENTRE DE COMMUNICATION</p>
                <h1>Mes notifications</h1>
                <p>Retrouvez les mises à jour de vos demandes, devis, commandes, paiements et échanges avec vos professionnels.</p>
            </div>

            @if (! $archived && $unreadCount > 0)
                <form method="POST" action="{{ route('client.notifications.read-all') }}">
                    @csrf
                    <button class="btn btn-secondary" type="submit">
                        <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                        Tout marquer comme lu
                    </button>
                </form>
            @endif
        </header>

        @if (session('success') && session('success') !== 'Vos préférences de notification ont été enregistrées.')
            <div class="client-panel notification-alert" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="client-panel notification-alert notification-alert--error" role="alert">
                <strong>Certains choix n'ont pas été enregistrés.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="notification-overview" aria-label="Résumé des notifications">
            <div class="client-panel notification-overview__card">
                <span class="notification-overview__icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
                <span><strong>{{ $unreadCount }}</strong><small>Non lues</small></span>
            </div>
            <a class="client-panel notification-overview__card notification-overview__card--link"
               href="{{ route('client.notifications.index', ['status' => 'all', 'archived' => '0']) }}" @if (! $archived) aria-current="page" @endif>
                <span class="notification-overview__icon" aria-hidden="true"><i class="fa-solid fa-inbox"></i></span>
                <span><strong>Boîte de réception</strong><small>Vos notifications actives</small></span>
            </a>
            <a class="client-panel notification-overview__card notification-overview__card--link"
               href="{{ route('client.notifications.index', ['archived' => '1']) }}" @if ($archived) aria-current="page" @endif>
                <span class="notification-overview__icon" aria-hidden="true"><i class="fa-solid fa-box-archive"></i></span>
                <span><strong>Archives</strong><small>Historique conservé</small></span>
            </a>
        </section>

        <section class="client-panel notification-filter-panel" aria-label="Rechercher et filtrer les notifications">
            <form method="GET" action="{{ route('client.notifications.index') }}" class="notification-filters">
                <label>
                    <span>Recherche</span>
                    <input type="search" name="q" value="{{ $search }}" maxlength="100"
                           placeholder="Titre ou contenu…" autocomplete="off">
                </label>
                <label>
                    <span>Catégorie</span>
                    <select name="category">
                        <option value="all" @selected($category === 'all')>Toutes les catégories</option>
                        <option value="service_request" @selected($category === 'service_request')>Demandes</option>
                        <option value="quotation" @selected($category === 'quotation')>Devis</option>
                        <option value="order" @selected($category === 'order')>Commandes</option>
                        <option value="payment" @selected($category === 'payment')>Paiements</option>
                        <option value="message" @selected($category === 'message')>Messages</option>
                        <option value="review" @selected($category === 'review')>Avis</option>
                        <option value="account" @selected($category === 'account')>Compte et sécurité</option>
                    </select>
                </label>
                <label>
                    <span>État</span>
                    <select name="status">
                        <option value="all" @selected($status === 'all')>Toutes</option>
                        <option value="unread" @selected($status === 'unread')>Non lues</option>
                        <option value="read" @selected($status === 'read')>Lues</option>
                    </select>
                </label>
                <label>
                    <span>Emplacement</span>
                    <select name="archived">
                        <option value="0" @selected(! $archived)>Boîte de réception</option>
                        <option value="1" @selected($archived)>Archives</option>
                    </select>
                </label>
                <div class="notification-filter-actions">
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Appliquer
                    </button>
                    <a class="notification-reset-link" href="{{ route('client.notifications.index', ['archived' => $archived ? '1' : '0']) }}">Réinitialiser les filtres</a>
                </div>
            </form>
        </section>

        @if ($search !== '' || $category !== 'all' || $status !== 'all')
            <div class="notification-active-filters" aria-label="Filtres actifs">
                <strong>Filtres actifs</strong>
                @if ($search !== '')<span>Recherche : {{ $search }}</span>@endif
                @if ($category !== 'all')<span>Catégorie : {{ match ($category) { 'service_request' => 'Demandes', 'quotation' => 'Devis', 'order' => 'Commandes', 'payment' => 'Paiements', 'message' => 'Messages', 'review' => 'Avis', 'account' => 'Compte et sécurité', default => 'Plateforme' } }}</span>@endif
                @if ($status !== 'all')<span>État : {{ $status === 'unread' ? 'Non lues' : 'Lues' }}</span>@endif
            </div>
        @endif

        <section class="notification-results" aria-labelledby="notification-results-title">
            <div class="notification-results__heading">
                <div>
                    <p class="client-module__eyebrow">{{ $archived ? 'HISTORIQUE' : 'VOTRE ACTIVITÉ' }}</p>
                    <h2 id="notification-results-title">{{ $archived ? 'Notifications archivées' : 'Notifications récentes' }}</h2>
                </div>
                <span>{{ $notifications->total() }} résultat(s)</span>
            </div>

            @if ($notifications->isEmpty())
                <div class="client-panel notification-empty" role="status">
                    <span class="notification-empty__icon" aria-hidden="true">
                        <i class="fa-regular {{ $archived ? 'fa-folder-open' : 'fa-bell-slash' }}"></i>
                    </span>
                    <h3>{{ $search !== '' || $category !== 'all' || $status !== 'all' ? 'Aucun résultat pour ces filtres' : ($archived ? 'Aucune notification archivée' : 'Vous êtes à jour') }}</h3>
                    <p>
                        @if ($search !== '' || $category !== 'all' || $status !== 'all')
                            Essayez de modifier vos filtres. Les notifications correspondant à votre recherche apparaîtront ici.
                        @else
                            @if ($archived)
                                Les notifications archivées sont conservées ici. Vous pouvez revenir à la boîte de réception pour consulter les nouvelles mises à jour.
                            @else
                                Vous êtes à jour. Les événements importants de votre activité apparaîtront ici.
                            @endif
                        @endif
                    </p>
                    @if ($search !== '' || $category !== 'all' || $status !== 'all')
                        <a class="btn btn-secondary" href="{{ route('client.notifications.index', ['archived' => $archived ? '1' : '0']) }}">Effacer les filtres</a>
                    @endif
                </div>
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
                                'account', 'profile_updated', 'password_changed', 'password_reset', 'email_changed' => 'fa-shield-halved',
                                default => 'fa-bell',
                            };
                            $linkLabel = match ($action) {
                                'message' => 'Consulter le message',
                                'review' => 'Consulter l’avis',
                                'payment' => 'Voir le paiement',
                                'order' => 'Voir la commande',
                                'quotation' => 'Consulter le devis',
                                'service_request' => 'Voir la demande',
                                default => 'Consulter PROXIWORK',
                            };
                            $priority = (string) ($data['priority'] ?? 'normal');
                            $categoryLabel = match ($action) {
                                'service_request' => 'Demande',
                                'quotation' => 'Devis',
                                'order' => 'Commande',
                                'payment' => 'Paiement',
                                'message' => 'Message',
                                'review' => 'Avis',
                                'account', 'profile_updated', 'password_changed', 'password_reset', 'email_changed' => 'Compte et sécurité',
                                default => 'Plateforme',
                            };
                        @endphp

                        <article class="client-panel notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                            <div class="notification-icon" aria-hidden="true">
                                <i class="fa-solid {{ $icon }}"></i>
                            </div>

                            <div class="notification-body">
                                <div class="notification-heading">
                                    <h3>{{ $data['title'] ?? 'Notification PROXIWORK' }}</h3>
                                    @if (! $notification->read_at)
                                        <span class="unread-dot" aria-hidden="true"></span>
                                        <span class="notification-state">Non lue</span>
                                    @else
                                        <span class="notification-state notification-state--read">Lue</span>
                                    @endif
                                </div>

                                <p>{{ filled($data['message'] ?? null) ? $data['message'] : 'Une mise à jour de votre activité est disponible.' }}</p>

                                <div class="notification-meta">
                                    <span class="notification-category">{{ $categoryLabel }}</span>
                                    @if ($priority === 'critical')
                                        <span class="notification-priority notification-priority--critical">Alerte de sécurité</span>
                                    @elseif ($priority === 'important')
                                        <span class="notification-priority">Important</span>
                                    @endif
                                    <time datetime="{{ $notification->created_at?->toIso8601String() }}">
                                        {{ $notification->created_at?->diffForHumans() }}
                                    </time>
                                </div>

                                <div class="notification-item__actions">
                                    @if ($notification->safe_url)
                                        <a class="btn btn-secondary btn-sm" href="{{ $notification->safe_url }}">{{ $linkLabel }}</a>
                                    @endif

                                    @if (! $archived)
                                        @unless ($notification->read_at)
                                            <form method="POST" action="{{ route('client.notifications.read', $notification->id) }}">
                                                @csrf
                                                <button class="link-button" type="submit">Marquer comme lue</button>
                                            </form>
                                        @endunless
                                        <form method="POST" action="{{ route('client.notifications.archive', $notification->id) }}">
                                            @csrf
                                            <button class="link-button" type="submit">Archiver</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('client.notifications.restore', $notification->id) }}">
                                            @csrf
                                            <button class="link-button" type="submit">Restaurer</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <nav class="notification-pagination" aria-label="Pagination des notifications">
                    {{ $notifications->links() }}
                </nav>
            @endif
        </section>

        <section class="client-panel notification-preferences" aria-labelledby="notification-preferences-title">
            <div class="notification-preferences__heading">
                <span class="notification-overview__icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
                <div>
                    <p class="client-module__eyebrow">VOS CHOIX</p>
                    <h2 id="notification-preferences-title">Préférences de notification</h2>
                    <p>Les notifications internes restent activées pour conserver votre historique PROXIWORK.</p>
                </div>
            </div>

            @if (session('success') === 'Vos préférences de notification ont été enregistrées.')
                <div class="notification-alert notification-alert--success" role="status">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('client.notifications.preferences.update') }}" class="notification-preferences__form">
                @csrf
                @method('PATCH')

                <label class="notification-preference">
                    <span><strong>E-mails de suivi</strong><small>Recevoir les notifications transactionnelles par e-mail. Les alertes critiques de sécurité peuvent être envoyées indépendamment de ce choix.</small></span>
                    <input type="hidden" name="email_enabled" value="0">
                    <input type="checkbox" name="email_enabled" value="1" @checked((bool) old('email_enabled', $preferences->email_enabled))>
                </label>

                <label class="notification-preference">
                    <span><strong>SMS</strong><small>Enregistré comme préférence. L'envoi réel reste indisponible tant qu'un fournisseur compatible avec la RDC n'est pas configuré.</small></span>
                    <input type="hidden" name="sms_enabled" value="0">
                    <input type="checkbox" name="sms_enabled" value="1" @checked((bool) old('sms_enabled', $preferences->sms_enabled))>
                </label>

                <label class="notification-preference">
                    <span><strong>Notifications push</strong><small>Enregistré comme préférence. La livraison push sera activée après configuration du service et autorisation de l'appareil.</small></span>
                    <input type="hidden" name="push_enabled" value="0">
                    <input type="checkbox" name="push_enabled" value="1" @checked((bool) old('push_enabled', $preferences->push_enabled))>
                </label>

                <div class="notification-preferences__actions">
                    <button class="btn btn-primary" type="submit">Enregistrer mes préférences</button>
                </div>
            </form>
        </section>
    </div>
@endsection
