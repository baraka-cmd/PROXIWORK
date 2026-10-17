@extends('layouts.professional')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/client/messages.css', 'resources/css/pages/professional/reviews-messages.css'])
    @endunless
@endpush

@section('page_title', 'Messages')
@section('page_description', 'Échangez avec les clients au sujet de leurs demandes et commandes, dans un espace privé.')

@section('content')
    <div class="client-messages-page">
        @if (session('success'))
            <div class="feedback feedback--success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="messages-feedback messages-feedback--error" role="alert">
                <strong>La demande n'a pas pu être traitée.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="client-panel messages-filter-panel" aria-label="Rechercher et filtrer les conversations">
            <form method="GET" action="{{ route('professional.messages') }}" class="messages-filters">
                <label>
                    <span>Recherche</span>
                    <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Nom, message ou référence…" autocomplete="off">
                </label>
                <label>
                    <span>Contexte</span>
                    <select name="category">
                        <option value="all" @selected($category === 'all')>Tous les contextes</option>
                        <option value="requests" @selected($category === 'requests')>Demandes</option>
                        <option value="orders" @selected($category === 'orders')>Commandes</option>
                        <option value="general" @selected($category === 'general')>Générales</option>
                    </select>
                </label>
                <label>
                    <span>Messages</span>
                    <select name="status">
                        <option value="all" @selected($status === 'all')>Toutes</option>
                        <option value="unread" @selected($status === 'unread')>Avec des non-lus</option>
                    </select>
                </label>
                <label>
                    <span>Emplacement</span>
                    <select name="archived">
                        <option value="0" @selected(! $archived)>Boîte de réception</option>
                        <option value="1" @selected($archived)>Archives</option>
                    </select>
                </label>
                <div class="messages-filter-actions">
                    <button class="button button--primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Rechercher</button>
                    <a class="messages-reset-link" href="{{ route('professional.messages') }}">Réinitialiser</a>
                </div>
            </form>
        </section>

        <section class="client-panel conversation-list-panel" aria-label="Liste des conversations">
            <div class="messages-list-heading">
                <div>
                    <h2>{{ $archived ? 'Conversations archivées' : 'Vos conversations' }}</h2>
                    <p>{{ $conversations->total() }} conversation(s) au total{{ $conversations->total() !== $conversations->count() ? ' · page '.$conversations->currentPage().' sur '.$conversations->lastPage() : '' }}</p>
                </div>
            </div>

            @if ($conversations->isEmpty())
                <div class="message-empty">
                    <span class="message-empty__icon"><i class="fa-regular fa-comments" aria-hidden="true"></i></span>
                    <h3>{{ $archived ? 'Aucune conversation archivée' : 'Aucune conversation pour le moment' }}</h3>
                    @if ($search !== '' || $category !== 'all' || $status !== 'all')
                        <p>Aucune conversation ne correspond aux critères sélectionnés. Essayez d’élargir la recherche ou de réinitialiser les filtres.</p>
                        <a class="button button--secondary" href="{{ route('professional.messages', ['archived' => $archived ? '1' : '0']) }}">Effacer les filtres</a>
                    @else
                        <p>{{ $archived ? 'Les conversations archivées et leur historique restent consultables ici.' : 'Les conversations initiées par vos clients apparaîtront ici.' }}</p>
                    @endif
                </div>
            @else
                <div class="conversation-list">
                    @foreach ($conversations as $conversation)
                        @php
                            $clientName = $conversation->client?->name ?? 'Client';
                            $lastMessage = $conversation->lastMessage;
                            $preview = trim((string) ($lastMessage?->body ?? ''));
                            if ($preview === '' && $lastMessage?->attachments?->isNotEmpty()) {
                                $preview = 'Pièce jointe';
                            }
                            if ($preview === '') {
                                $preview = 'Démarrez la conversation';
                            }
                        @endphp
                        <article class="conversation-row {{ $conversation->unread_count > 0 ? 'conversation-row--unread' : '' }}">
                            <a class="conversation-row__main" href="{{ route('professional.messages.show', $conversation) }}">
                                <span class="conversation-avatar" aria-hidden="true">{{ strtoupper(mb_substr($clientName, 0, 1)) }}</span>
                                <span class="conversation-content">
                                    <span class="conversation-top">
                                        <strong>{{ $clientName }}</strong>
                                        @if ($conversation->unread_count > 0)
                                            <span class="unread-count" aria-label="{{ $conversation->unread_count }} message(s) non lu(s)">{{ $conversation->unread_count }}</span>
                                        @endif
                                    </span>
                                    <span class="conversation-preview">{{ \Illuminate\Support\Str::limit($preview, 100) }}</span>
                                    <span class="conversation-tags">
                                        @if ($conversation->order_id)
                                            <span class="message-context-tag">Commande #{{ $conversation->order_id }}</span>
                                        @elseif ($conversation->service_request_id)
                                            <span class="message-context-tag">Demande #{{ $conversation->service_request_id }}</span>
                                        @else
                                            <span class="message-context-tag">Conversation générale</span>
                                        @endif
                                        @if ($conversation->is_pinned)
                                            <span class="message-context-tag"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Épinglée</span>
                                        @endif
                                    </span>
                                </span>
                                <time datetime="{{ $conversation->updated_at?->toIso8601String() }}">{{ $conversation->updated_at?->diffForHumans() }}</time>
                            </a>
                            <div class="conversation-row__actions">
                                <form method="POST" action="{{ route('professional.messages.pin', $conversation) }}">
                                    @csrf
                                    <input type="hidden" name="pinned" value="{{ $conversation->is_pinned ? '0' : '1' }}">
                                    <button class="icon-action" type="submit" aria-label="{{ $conversation->is_pinned ? 'Désépingler' : 'Épingler' }}" title="{{ $conversation->is_pinned ? 'Désépingler' : 'Épingler' }}">
                                        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('professional.messages.archive', $conversation) }}">
                                    @csrf
                                    <input type="hidden" name="archived" value="{{ $archived ? '0' : '1' }}">
                                    <button class="icon-action" type="submit" aria-label="{{ $archived ? 'Restaurer' : 'Archiver' }}" title="{{ $archived ? 'Restaurer' : 'Archiver' }}">
                                        <i class="fa-solid {{ $archived ? 'fa-box-open' : 'fa-box-archive' }}" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="messages-pagination">{{ $conversations->links() }}</div>
            @endif
        </section>
    </div>
@endsection
