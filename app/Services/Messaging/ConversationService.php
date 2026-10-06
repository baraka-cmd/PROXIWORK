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
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function findOrCreateForClient(
        User $client,
        ProfessionalProfile $professional,
        ?ServiceRequest $serviceRequest = null,
        ?Order $order = null,
    ): Conversation {
        return $this->database->transaction(function () use ($client, $professional, $serviceRequest, $order): Conversation {
            if ($client->hasRole('client') === false) {
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

            $conversation = Conversation::query()
                ->where('type', ConversationType::CLIENT_PROFESSIONAL)
                ->where('client_id', $client->getKey())
                ->where('professional_id', $professional->getKey())
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                $conversation = Conversation::query()->create([
                    'type' => ConversationType::CLIENT_PROFESSIONAL,
                    'status' => ConversationStatus::OPEN,
                    'client_id' => $client->getKey(),
                    'professional_id' => $professional->getKey(),
                    'service_request_id' => $serviceRequest?->getKey(),
                    'order_id' => $order?->getKey(),
                ]);

                $conversation->participants()->attach([
                    $client->getKey(),
                    $professional->user_id,
                ]);
            }

            return $conversation->load(['client', 'professional.user', 'lastMessage']);
        }, attempts: 3);
    }

    public function listForUser(User $user, int $perPage = 20): CursorPaginator
    {
        $perPage = max(1, min($perPage, 50));

        $paginator = Conversation::query()
            ->whereHas('participants', function ($query) use ($user): void {
                $query->where('users.id', $user->getKey())->whereNull('left_at');
            })
            ->with(['professional.user', 'client', 'lastMessage.sender'])
            ->orderByDesc('id')
            ->cursorPaginate($perPage);

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

            foreach ($paginator->items() as $conversation) {
                $conversation->setAttribute('unread_count', (int) ($unread[$conversation->getKey()] ?? 0));
            }
        }

        return $paginator;
    }

    public function showForUser(Conversation $conversation, User $user): Conversation
    {
        $this->assertParticipant($conversation, $user);

        return $conversation->load([
            'client',
            'professional.user',
            'lastMessage.sender',
        ]);
    }

    public function listMessages(Conversation $conversation, User $user, int $perPage = 50): CursorPaginator
    {
        $this->assertParticipant($conversation, $user);

        return $conversation->messages()
            ->with('sender')
            ->orderByDesc('id')
            ->cursorPaginate(max(1, min($perPage, 100)));
    }

    public function sendMessage(Conversation $conversation, User $sender, string $body): Message
    {
        return $this->database->transaction(function () use ($conversation, $sender, $body): Message {
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($conversation->getKey());
            $this->assertParticipant($conversation, $sender);

            if ($conversation->status !== ConversationStatus::OPEN) {
                throw ValidationException::withMessages([
                    'conversation' => 'Cette conversation n’accepte plus de nouveaux messages.',
                ]);
            }

            $body = trim($body);

            $duplicate = Message::query()
                ->where('conversation_id', $conversation->getKey())
                ->where('sender_id', $sender->getKey())
                ->where('body', $body)
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'body' => 'Ce message vient déjà d’être envoyé.',
                ]);
            }

            $message = Message::query()->create([
                'conversation_id' => $conversation->getKey(),
                'sender_id' => $sender->getKey(),
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_id' => $message->getKey(),
            ])->save();

            return $message->load('sender');
        }, attempts: 3);
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

    private function assertParticipant(Conversation $conversation, User $user): void
    {
        $exists = DB::table('conversation_participants')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();

        if (!$exists) {
            abort(403, 'Vous n’avez pas accès à cette conversation.');
        }
    }

    private function validateContext(
        User $client,
        ProfessionalProfile $professional,
        ?ServiceRequest $serviceRequest,
        ?Order $order,
    ): void {
        if ($order !== null) {
            if ($order->client_id !== $client->getKey() || $order->professional_id !== $professional->getKey()) {
                throw ValidationException::withMessages([
                    'order' => 'La commande ne correspond pas aux participants de la conversation.',
                ]);
            }
        }

        if ($serviceRequest !== null) {
            if ($serviceRequest->client_id !== $client->getKey() || $serviceRequest->professional_id !== $professional->getKey()) {
                throw ValidationException::withMessages([
                    'service_request' => 'La demande ne correspond pas aux participants de la conversation.',
                ]);
            }
        }

        if ($order !== null && $serviceRequest !== null && $order->service_request_id !== $serviceRequest->getKey()) {
            throw ValidationException::withMessages([
                'service_request' => 'La demande ne correspond pas à la commande.',
            ]);
        }
    }
}
