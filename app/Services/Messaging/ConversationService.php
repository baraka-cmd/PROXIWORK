<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Notification\TransactionalNotificationService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConversationService
{
    private readonly DatabaseManager $database;

    public function __construct(
        DatabaseManager $database,
        private readonly TransactionalNotificationService $notificationService,
    ) {
        $this->database = $database;
    }

    public function findOrCreateForClient(
        User $client,
        ProfessionalProfile $professional,
        ?ServiceRequest $serviceRequest = null,
        ?Order $order = null,
    ): Conversation {
        return $this->database->transaction(function () use ($client, $professional, $serviceRequest, $order): Conversation {
            if (! $client->hasRole('client')) {
                throw ValidationException::withMessages([
                    'user' => 'Seul un client peut démarrer une conversation avec un professionnel.',
                ]);
            }

            $professional = ProfessionalProfile::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($professional->getKey());

            if ($professional->user_id === $client->getKey()) {
                throw ValidationException::withMessages([
                    'professional' => 'Vous ne pouvez pas démarrer une conversation avec vous-même.',
                ]);
            }

            $this->validateContext($client, $professional, $serviceRequest, $order);
            $contextKey = $this->contextKey($serviceRequest, $order);

            $conversation = Conversation::query()
                ->where('type', ConversationType::CLIENT_PROFESSIONAL)
                ->where('client_id', $client->getKey())
                ->where('professional_id', $professional->getKey())
                ->where('context_key', $contextKey)
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                $conversation = Conversation::query()->create([
                    'type' => ConversationType::CLIENT_PROFESSIONAL,
                    'status' => ConversationStatus::OPEN,
                    'client_id' => $client->getKey(),
                    'professional_id' => $professional->getKey(),
                    'context_key' => $contextKey,
                    'service_request_id' => $serviceRequest?->getKey() ?? $order?->service_request_id,
                    'order_id' => $order?->getKey(),
                ]);

                $conversation->participants()->attach([
                    $client->getKey(),
                    $professional->user_id,
                ]);
            } elseif ($conversation->status === ConversationStatus::CLOSED) {
                $conversation->forceFill(['status' => ConversationStatus::OPEN])->save();
            } elseif ($conversation->status === ConversationStatus::BLOCKED) {
                throw ValidationException::withMessages([
                    'conversation' => 'Cette conversation est bloquée et ne peut pas être rouverte.',
                ]);
            }

            return $conversation->load(['client', 'professional.user', 'lastMessage']);
        }, attempts: 3);
    }

    /**
     * List only conversations in which the authenticated user is an active participant.
     *
     * Supported filters are deliberately constrained by the web/API request validators.
     */
    public function listForUser(User $user, int $perPage = 20, array $filters = []): CursorPaginator
    {
        $perPage = max(1, min($perPage, 50));
        $query = Conversation::query()
            ->join('conversation_participants as current_participant', function ($join) use ($user): void {
                $join->on('current_participant.conversation_id', '=', 'conversations.id')
                    ->where('current_participant.user_id', '=', $user->getKey())
                    ->whereNull('current_participant.left_at');
            })
            ->select([
                'conversations.*',
                DB::raw('CASE WHEN current_participant.pinned_at IS NULL THEN 0 ELSE 1 END AS is_pinned_sort'),
            ])
            ->with(['professional.user', 'client', 'lastMessage.sender', 'lastMessage.attachments']);

        if (($filters['archived'] ?? false) === true) {
            $query->whereNotNull('current_participant.archived_at');
        } else {
            $query->whereNull('current_participant.archived_at');
        }

        $category = $filters['category'] ?? 'all';
        if ($category === 'requests') {
            $query->whereNotNull('service_request_id');
        } elseif ($category === 'orders') {
            $query->whereNotNull('order_id');
        } elseif ($category === 'general') {
            $query->whereNull('service_request_id')->whereNull('order_id');
        }

        if (($filters['status'] ?? 'all') === 'unread') {
            $query->whereExists(function ($subquery) use ($user): void {
                $subquery->selectRaw('1')
                    ->from('messages as unread_messages')
                    ->join('conversation_participants as unread_participants', function ($join) use ($user): void {
                        $join->on('unread_participants.conversation_id', '=', 'unread_messages.conversation_id')
                            ->where('unread_participants.user_id', '=', $user->getKey())
                            ->whereNull('unread_participants.left_at');
                    })
                    ->whereColumn('unread_messages.conversation_id', 'conversations.id')
                    ->where('unread_messages.sender_id', '!=', $user->getKey())
                    ->whereRaw('unread_messages.id > COALESCE(unread_participants.last_read_message_id, 0)');
            });
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->whereHas('professional.user', fn ($participant) => $participant->where('name', 'like', $like))
                    ->orWhereHas('client', fn ($participant) => $participant->where('name', 'like', $like))
                    ->orWhereHas('messages', fn ($message) => $message->where('body', 'like', $like));

                if (ctype_digit($search)) {
                    $builder->orWhere('service_request_id', (int) $search)
                        ->orWhere('order_id', (int) $search);
                }
            });
        }

        // The non-null computed pin rank keeps cursor pagination stable across SQL engines.
        $query->orderByDesc('is_pinned_sort')
            ->orderByDesc('conversations.updated_at')
            ->orderByDesc('conversations.id');

        $paginator = $query->cursorPaginate($perPage);
        $ids = collect($paginator->items())->pluck('id')->all();

        if ($ids !== []) {
            $unread = DB::table('messages')
                ->join('conversation_participants as cp', function ($join) use ($user): void {
                    $join->on('cp.conversation_id', '=', 'messages.conversation_id')
                        ->where('cp.user_id', '=', $user->getKey())
                        ->whereNull('cp.left_at');
                })
                ->whereIn('messages.conversation_id', $ids)
                ->where('messages.sender_id', '!=', $user->getKey())
                ->whereRaw('messages.id > COALESCE(cp.last_read_message_id, 0)')
                ->groupBy('messages.conversation_id')
                ->select('messages.conversation_id', DB::raw('COUNT(*) AS unread_count'))
                ->pluck('unread_count', 'conversation_id');

            $participantStates = DB::table('conversation_participants')
                ->where('user_id', $user->getKey())
                ->whereIn('conversation_id', $ids)
                ->get(['conversation_id', 'pinned_at', 'archived_at'])
                ->keyBy('conversation_id');

            foreach ($paginator->items() as $conversation) {
                $state = $participantStates->get($conversation->getKey());
                $conversation->setAttribute('unread_count', (int) ($unread[$conversation->getKey()] ?? 0));
                $conversation->setAttribute('is_pinned', $state?->pinned_at !== null);
                $conversation->setAttribute('is_archived', $state?->archived_at !== null);
            }
        }

        return $paginator;
    }

    public function showForUser(Conversation $conversation, User $user): Conversation
    {
        $this->assertParticipant($conversation, $user);

        $conversation->load([
            'client',
            'professional.user',
            'order',
            'serviceRequest',
            'lastMessage.sender',
        ]);

        $recipientId = (string) $conversation->client_id === (string) $user->getKey()
            ? $conversation->professional?->user_id
            : $conversation->client_id;
        $recipientState = $recipientId === null
            ? null
            : DB::table('conversation_participants')
                ->where('conversation_id', $conversation->getKey())
                ->where('user_id', $recipientId)
                ->whereNull('left_at')
                ->first(['last_read_message_id', 'share_read_receipts']);
        $recipientReadMarker = $recipientState !== null && $recipientState->share_read_receipts
            ? $recipientState->last_read_message_id
            : null;

        $conversation->setAttribute('recipient_last_read_message_id', $recipientReadMarker);
        $participantState = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->first(['pinned_at', 'archived_at', 'share_read_receipts']);
        $conversation->setAttribute('is_pinned', $participantState?->pinned_at !== null);
        $conversation->setAttribute('is_archived', $participantState?->archived_at !== null);
        $conversation->setAttribute('share_read_receipts', (bool) ($participantState?->share_read_receipts ?? true));

        return $conversation;
    }

    public function listMessages(Conversation $conversation, User $user, int $perPage = 50): CursorPaginator
    {
        $this->assertParticipant($conversation, $user);

        $messages = $conversation->messages()
            ->with(['sender', 'attachments', 'replyTo.sender'])
            ->orderByDesc('id')
            ->cursorPaginate(max(1, min($perPage, 100)));

        $this->applyReadState($conversation, $user, $messages->items());

        return $messages;
    }

    public function messagesAfter(Conversation $conversation, User $user, int $afterId, int $limit = 50): EloquentCollection
    {
        $this->assertParticipant($conversation, $user);

        $messages = $conversation->messages()
            ->where('id', '>', max(0, $afterId))
            ->with(['sender', 'attachments'])
            ->orderBy('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        $this->applyReadState($conversation, $user, $messages);

        return $messages;
    }

    /**
     * Persist a message and its private attachments atomically from the user's perspective.
     * A client-supplied UUID is an idempotency key, not an author or timestamp.
     */
    public function sendMessage(
        Conversation $conversation,
        User $sender,
        string $body,
        ?string $idempotencyKey = null,
        array $attachments = [],
        ?int $replyToMessageId = null,
    ): Message {
        $storedPaths = [];

        try {
            return $this->database->transaction(function () use (
                $conversation,
                $sender,
                $body,
                $idempotencyKey,
                $attachments,
                $replyToMessageId,
                &$storedPaths,
            ): Message {
                $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->getKey());
                $this->assertParticipant($conversation, $sender);

                if ($conversation->status !== ConversationStatus::OPEN) {
                    throw ValidationException::withMessages([
                        'conversation' => 'Cette conversation n’accepte plus de nouveaux messages.',
                    ]);
                }

                $body = trim($body);

                if ($body === '' && $attachments === []) {
                    throw ValidationException::withMessages([
                        'body' => 'Écrivez un message ou joignez au moins un fichier autorisé.',
                    ]);
                }

                if ($idempotencyKey !== null) {
                    $existing = Message::query()
                        ->where('conversation_id', $conversation->getKey())
                        ->where('sender_id', $sender->getKey())
                        ->where('idempotency_key', $idempotencyKey)
                        ->with(['sender', 'attachments', 'replyTo.sender'])
                        ->first();

                    if ($existing !== null) {
                        $existingAttachmentHashes = $existing->attachments
                            ->pluck('sha256')
                            ->sort()
                            ->values()
                            ->all();
                        $incomingAttachmentHashes = collect($attachments)
                            ->map(fn ($file): string => hash_file('sha256', $file->getRealPath()) ?: '')
                            ->sort()
                            ->values()
                            ->all();

                        if (
                            $existing->body !== $body
                            || (string) ($existing->reply_to_message_id ?? '') !== (string) ($replyToMessageId ?? '')
                            || $existingAttachmentHashes !== $incomingAttachmentHashes
                        ) {
                            throw ValidationException::withMessages([
                                'idempotency_key' => 'Cette clé a déjà été utilisée pour un contenu différent.',
                            ]);
                        }

                        return $existing;
                    }
                }

                if ($replyToMessageId !== null) {
                    $replyExists = Message::query()
                        ->where('conversation_id', $conversation->getKey())
                        ->whereKey($replyToMessageId)
                        ->exists();

                    if (! $replyExists) {
                        throw ValidationException::withMessages([
                            'reply_to_message_id' => 'Le message auquel vous répondez n’appartient pas à cette conversation.',
                        ]);
                    }
                }

                $message = Message::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'sender_id' => $sender->getKey(),
                    'body' => $body,
                    'idempotency_key' => $idempotencyKey,
                    'reply_to_message_id' => $replyToMessageId,
                ]);

                foreach ($attachments as $file) {
                    $path = Storage::disk('local')->putFile('messages/'.$conversation->getKey(), $file);

                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('Le stockage privé du fichier a échoué.');
                    }

                    $storedPaths[] = $path;
                    $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                    $originalName = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '');
                    $originalName = Str::limit($originalName !== '' ? $originalName : 'piece-jointe', 250, '');

                    $message->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $originalName,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => (int) $file->getSize(),
                        'sha256' => hash_file('sha256', $file->getRealPath()),
                    ]);
                }

                $conversation->forceFill([
                    'last_message_id' => $message->getKey(),
                ])->save();

                $conversation->loadMissing(['client', 'professional.user']);
                $recipient = (string) $conversation->client_id === (string) $sender->getKey()
                    ? $conversation->professional?->user
                    : $conversation->client;

                if ($recipient !== null && DB::table('conversation_participants')
                    ->where('conversation_id', $conversation->getKey())
                    ->where('user_id', $recipient->getKey())
                    ->whereNull('left_at')
                    ->exists()) {
                    // A new inbound message returns the recipient's archived thread to the inbox.
                    DB::table('conversation_participants')
                        ->where('conversation_id', $conversation->getKey())
                        ->where('user_id', $recipient->getKey())
                        ->update(['archived_at' => null, 'updated_at' => now()]);

                    $recipientIsClient = (string) $recipient->getKey() === (string) $conversation->client_id;
                    $destination = route(
                        $recipientIsClient ? 'client.messages.show' : 'professional.messages.show',
                        $conversation,
                        true,
                    );

                    $this->notificationService->messageReceived(
                        $recipient,
                        (int) $conversation->getKey(),
                        (int) $message->getKey(),
                        $destination,
                    );
                }

                return $message->load(['sender', 'attachments', 'replyTo.sender']);
            }, attempts: 1);
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function markAsRead(Conversation $conversation, User $user): void
    {
        $this->database->transaction(function () use ($conversation, $user): void {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->getKey());
            $this->assertParticipant($conversation, $user);

            $latestMessageId = $conversation->messages()->max('id');
            $participant = DB::table('conversation_participants')
                ->where('conversation_id', $conversation->getKey())
                ->where('user_id', $user->getKey())
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($participant === null) {
                throw ValidationException::withMessages([
                    'conversation' => 'Vous ne participez plus à cette conversation.',
                ]);
            }

            if ($latestMessageId !== null && ($participant->last_read_message_id === null || $latestMessageId > $participant->last_read_message_id)) {
                DB::table('conversation_participants')
                    ->where('conversation_id', $conversation->getKey())
                    ->where('user_id', $user->getKey())
                    ->update([
                        'last_read_message_id' => $latestMessageId,
                        'updated_at' => now(),
                    ]);
            }
        }, attempts: 3);
    }

    public function setPinned(Conversation $conversation, User $user, bool $pinned): void
    {
        $this->updateParticipantState($conversation, $user, [
            'pinned_at' => $pinned ? now() : null,
        ]);
    }

    public function setArchived(Conversation $conversation, User $user, bool $archived): void
    {
        $this->updateParticipantState($conversation, $user, [
            'archived_at' => $archived ? now() : null,
        ]);
    }

    public function setReadReceipts(Conversation $conversation, User $user, bool $enabled): void
    {
        $this->updateParticipantState($conversation, $user, ['share_read_receipts' => $enabled]);
    }

    public function blockConversation(Conversation $conversation, User $user): void
    {
        $this->database->transaction(function () use ($conversation, $user): void {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->getKey());
            $this->assertParticipant($locked, $user);

            if ($locked->status === ConversationStatus::BLOCKED) {
                return;
            }

            $locked->forceFill(['status' => ConversationStatus::BLOCKED])->save();
        }, attempts: 3);
    }

    private function updateParticipantState(Conversation $conversation, User $user, array $changes): void
    {
        $this->database->transaction(function () use ($conversation, $user, $changes): void {
            $this->assertParticipant($conversation, $user);

            $updated = DB::table('conversation_participants')
                ->where('conversation_id', $conversation->getKey())
                ->where('user_id', $user->getKey())
                ->whereNull('left_at')
                ->update([...$changes, 'updated_at' => now()]);

            if ($updated !== 1) {
                abort(403, 'Vous ne participez plus à cette conversation.');
            }
        }, attempts: 3);
    }

    private function applyReadState(Conversation $conversation, User $viewer, iterable $messages): void
    {
        $recipientId = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', '!=', $viewer->getKey())
            ->whereNull('left_at')
            ->value('user_id');

        $recipientState = $recipientId === null
            ? null
            : DB::table('conversation_participants')
                ->where('conversation_id', $conversation->getKey())
                ->where('user_id', $recipientId)
                ->whereNull('left_at')
                ->first(['last_read_message_id', 'share_read_receipts']);
        $readMarker = $recipientState !== null && $recipientState->share_read_receipts
            ? $recipientState->last_read_message_id
            : null;

        foreach ($messages as $message) {
            $isOwnMessage = (string) $message->sender_id === (string) $viewer->getKey();
            $message->setAttribute(
                'is_read_by_recipient',
                $isOwnMessage && $readMarker !== null && (int) $message->getKey() <= (int) $readMarker,
            );
        }
    }

    private function assertParticipant(Conversation $conversation, User $user): void
    {
        $exists = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();

        if (! $exists) {
            abort(403, 'Vous n’avez pas accès à cette conversation.');
        }
    }

    private function contextKey(?ServiceRequest $serviceRequest, ?Order $order): string
    {
        if ($order !== null) {
            return 'order:'.$order->getKey();
        }

        if ($serviceRequest !== null) {
            return 'request:'.$serviceRequest->getKey();
        }

        return 'general';
    }

    private function validateContext(
        User $client,
        ProfessionalProfile $professional,
        ?ServiceRequest $serviceRequest,
        ?Order $order,
    ): void {
        if ($order !== null) {
            if ((string) $order->client_id !== (string) $client->getKey()
                || (string) $order->professional_id !== (string) $professional->getKey()) {
                throw ValidationException::withMessages([
                    'order' => 'La commande ne correspond pas aux participants de la conversation.',
                ]);
            }

            if ($serviceRequest !== null && (string) $order->service_request_id !== (string) $serviceRequest->getKey()) {
                throw ValidationException::withMessages([
                    'service_request' => 'La demande ne correspond pas à la commande.',
                ]);
            }
        }

        if ($serviceRequest !== null
            && ((string) $serviceRequest->client_id !== (string) $client->getKey()
                || (string) $serviceRequest->professional_id !== (string) $professional->getKey())) {
            throw ValidationException::withMessages([
                'service_request' => 'La demande ne correspond pas aux participants de la conversation.',
            ]);
        }
    }
}
