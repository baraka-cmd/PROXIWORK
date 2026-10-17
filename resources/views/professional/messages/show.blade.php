@extends('layouts.professional')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/client/messages.css', 'resources/css/pages/professional/reviews-messages.css', 'resources/js/pages/client/messages.js'])
    @endunless
@endpush

@section('page_title', 'Conversation')
@section('page_description', 'Échange privé et sécurisé avec votre client.')

@section('page_actions')
    <a class="button button--secondary" href="{{ route('professional.messages') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour aux messages
    </a>
@endsection

@section('content')
    <div class="client-messages-page client-message-thread-page">
        @if (session('success'))
            <div class="feedback feedback--success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="messages-feedback messages-feedback--error" role="alert">
                <strong>Votre message n'a pas été envoyé.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <header class="client-panel message-conversation-header">
            <a class="message-back-link" href="{{ route('professional.messages') }}" aria-label="Retour aux messages">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Messages</span>
            </a>
            <div class="message-contact">
                <span class="conversation-avatar" aria-hidden="true">{{ strtoupper(mb_substr(($conversation->client?->name ?? 'C'), 0, 1)) }}</span>
                <div>
                    <p class="client-module__eyebrow">CLIENT</p>
                    <h1>{{ $conversation->client?->name ?? 'Client' }}</h1>
                    <p>Conversation professionnelle PROXIWORK</p>
                </div>
            </div>
            <div class="message-header-actions">
                <form method="POST" action="{{ route('professional.messages.pin', $conversation) }}">
                    @csrf
                    <input type="hidden" name="pinned" value="{{ $conversation->is_pinned ?? false ? '0' : '1' }}">
                    <button class="button button--secondary" type="submit">
                        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                        {{ $conversation->is_pinned ?? false ? 'Désépingler' : 'Épingler' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('professional.messages.read-receipts', $conversation) }}">
                    @csrf
                    <input type="hidden" name="share_read_receipts" value="{{ $conversation->share_read_receipts ? '0' : '1' }}">
                    <button class="button button--secondary" type="submit" title="Contrôler le partage des accusés de lecture">
                        <i class="fa-solid fa-eye{{ $conversation->share_read_receipts ? '' : '-slash' }}" aria-hidden="true"></i>
                        {{ $conversation->share_read_receipts ? 'Désactiver accusés' : 'Activer accusés' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('professional.messages.archive', $conversation) }}">
                    @csrf
                    <input type="hidden" name="archived" value="1">
                    <button class="button button--secondary" type="submit"><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Archiver</button>
                </form>
                @if ($conversation->status->value !== 'blocked')
                    <form method="POST" action="{{ route('professional.messages.block', $conversation) }}" data-confirm data-confirm-title="Bloquer cette conversation ?" data-confirm-message="Aucun participant ne pourra envoyer de nouveaux messages. L’historique sera conservé." data-confirm-label="Bloquer">
                        @csrf
                        <button class="button button--secondary message-block-button" type="submit"><i class="fa-solid fa-ban" aria-hidden="true"></i> Bloquer</button>
                    </form>
                @endif
            </div>
        </header>

        <section class="client-panel message-context" aria-label="Contexte métier">
            <div>
                <span class="message-context__label">Contexte</span>
                @if ($conversation->order)
                    <strong>Commande #{{ $conversation->order->id }} · {{ $conversation->order->status?->value ?? 'En suivi' }}</strong>
                    <a href="{{ route('professional.orders.show', $conversation->order) }}">Consulter la commande <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                @elseif ($conversation->serviceRequest)
                    <strong>Demande #{{ $conversation->serviceRequest->id }} · {{ $conversation->serviceRequest->status?->value ?? 'En cours' }}</strong>
                    <a href="{{ route('professional.requests.show', $conversation->serviceRequest) }}">Consulter la demande <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                @else
                    <strong>Conversation générale</strong>
                @endif
            </div>
            <span class="message-context__status"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Messages privés entre participants autorisés</span>
        </section>

        <nav class="messages-history-nav" aria-label="Navigation dans l'historique des messages">
            @if ($messages->previousPageUrl())
                <a class="messages-history-link" href="{{ $messages->previousPageUrl() }}"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Messages plus récents</a>
            @endif
            @if ($messages->nextPageUrl())
                <a class="messages-history-link" href="{{ $messages->nextPageUrl() }}"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i> Messages précédents</a>
            @endif
        </nav>
        <section
            class="client-panel message-thread"
            aria-label="Historique des messages"
            role="log"
            aria-live="polite"
            aria-relevant="additions text"
            data-message-thread
            data-last-message-id="{{ $lastMessageId }}"
            data-user-id="{{ auth()->id() }}"
            data-polling-enabled="{{ request()->query('cursor') ? 'false' : 'true' }}"
            data-updates-url="{{ route('professional.messages.updates', $conversation) }}"
        >
            @php($previousMessageDate = null)
            @forelse (collect($messages->items())->reverse() as $message)
                @php($currentMessageDate = $message->created_at?->toDateString())
                @if ($currentMessageDate !== $previousMessageDate)
                    <div class="message-day-divider" data-message-day="{{ $currentMessageDate }}"><span>{{ $message->created_at?->isToday() ? 'Aujourd’hui' : ($message->created_at?->isYesterday() ? 'Hier' : $message->created_at?->translatedFormat('l d F Y')) }}</span></div>
                @endif
                @php($previousMessageDate = $currentMessageDate)
                <article class="message-bubble {{ (string) $message->sender_id === (string) auth()->id() ? 'is-mine' : '' }}" data-message-id="{{ $message->id }}">
                    <div class="message-bubble__meta">
                        <strong>{{ (string) $message->sender_id === (string) auth()->id() ? 'Vous' : ($message->sender?->name ?? 'Client') }}</strong>
                        <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('H:i') }}</time>
                    </div>
                    @if ($message->replyTo)
                        <blockquote class="message-reply-preview">
                            <strong>Réponse à {{ $message->replyTo->sender?->name ?? 'Participant' }}</strong>
                            <p>{{ \Illuminate\Support\Str::limit((string) $message->replyTo->body, 160) }}</p>
                        </blockquote>
                    @endif
                    @if (trim((string) $message->body) !== '')
                        <p class="message-bubble__body">{{ $message->body }}</p>
                    @endif
                    @if ($message->attachments->isNotEmpty())
                        <div class="message-attachments">
                            @foreach ($message->attachments as $attachment)
                                <a class="message-attachment" href="{{ route('professional.messages.attachments.download', $attachment) }}">
                                    <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                    <span>{{ $attachment->original_name }}</span>
                                    <small>{{ number_format($attachment->size / 1024, 0, ',', ' ') }} Ko</small>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <button class="message-reply-button" type="button"
                            data-reply-to="{{ $message->id }}"
                            data-reply-body="{{ \Illuminate\Support\Str::limit((string) ($message->body ?: 'Pièce jointe'), 160) }}"
                            data-reply-sender="{{ $message->sender?->name ?? 'Participant' }}">
                        <i class="fa-solid fa-reply" aria-hidden="true"></i> Répondre
                    </button>
                    @if ((string) $message->sender_id !== (string) auth()->id())
                        <details class="message-report-control">
                            <summary><i class="fa-solid fa-flag" aria-hidden="true"></i> Signaler</summary>
                            <form method="POST" action="{{ route('professional.messages.report', [$conversation, $message]) }}">
                                @csrf
                                <label for="report-reason-{{ $message->id }}">Motif</label>
                                <select id="report-reason-{{ $message->id }}" name="reason_code" required>
                            <option value="SPAM">Spam</option>
                            <option value="FRAUD">Fraude</option>
                            <option value="HARASSMENT">Harcèlement</option>
                            <option value="ABUSIVE_CONTENT">Contenu abusif</option>
                            <option value="INAPPROPRIATE_CONTENT">Contenu inapproprié</option>
                            <option value="FAKE_PROFILE">Faux profil</option>
                            <option value="FAKE_SERVICE">Faux service</option>
                            <option value="SCAM">Arnaque</option>
                            <option value="COPYRIGHT">Droits d’auteur</option>
                            <option value="OTHER">Autre</option>
                                </select>
                                <label for="report-description-{{ $message->id }}">Détails (facultatif)</label>
                                <textarea id="report-description-{{ $message->id }}" name="description" rows="2" maxlength="1000"></textarea>
                                <button class="btn btn-secondary" type="submit">Envoyer le signalement</button>
                            </form>
                        </details>
                    @endif
                    @if ((string) $message->sender_id === (string) auth()->id())
                        <span class="message-delivery-state">
                            @if ($conversation->recipient_last_read_message_id !== null && $message->id <= $conversation->recipient_last_read_message_id)
                                <i class="fa-solid fa-check-double" aria-hidden="true"></i> Lu
                            @else
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Envoyé
                            @endif
                        </span>
                    @endif
                </article>
            @empty
                <div class="message-empty" data-empty-thread>
                    <span class="message-empty__icon"><i class="fa-regular fa-comments" aria-hidden="true"></i></span>
                    <h2>Le début de votre échange</h2>
                    <p>Répondez au client en restant dans le contexte de sa prestation.</p>
                </div>
            @endforelse
            <p class="message-sync-status" data-message-sync-status role="status">Synchronisation en cours…</p>
        </section>

        @if ($conversation->status->value === 'open')
            <section class="client-panel message-composer-panel">
                <form method="POST" action="{{ route('professional.messages.store', $conversation) }}" class="message-composer" enctype="multipart/form-data" data-message-form>
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <label for="message-body">Votre message</label>
                    <textarea id="message-body" name="body" maxlength="5000" rows="3" placeholder="Écrire un message…">{{ old('body') }}</textarea>
                    <div class="message-composer__footer">
                        <div class="message-attachment-picker">
                            <label class="button button--secondary" for="message-attachments"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> Joindre des fichiers</label>
                            <input id="message-attachments" type="file" name="attachments[]" multiple
                                   accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,image/jpeg,image/png,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   data-attachment-input>
                            <small data-attachment-names>Aucun fichier sélectionné · 5 fichiers maximum · 10 Mo par fichier</small>
                        </div>
                        <span class="message-character-count" data-character-count>0 / 5 000</span>
                        <button class="button button--primary" type="submit" data-send-button><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Envoyer</button>
                    </div>
                    <p class="message-composer__privacy"><i class="fa-solid fa-lock" aria-hidden="true"></i> Les pièces jointes restent privées et accessibles uniquement aux participants autorisés.</p>
                </form>
            </section>
        @else
            <div class="notice-box">Cette conversation n'accepte plus de nouveaux messages.</div>
        @endif
    </div>
@endsection
