<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\ReportMessageRequest;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Http\Resources\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Report;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageController extends Controller
{
class MessageController extends Controller
{
    public function index(Request $request, ConversationService $conversationService): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::in(['all', 'requests', 'orders', 'general'])],
            'status' => ['nullable', 'string', Rule::in(['all', 'unread'])],
            'archived' => ['nullable', 'string', Rule::in(['0', '1'])],
        ]);
        $archived = ($filters['archived'] ?? '0') === '1';

        $conversations = $conversationService->listForUser(
            $request->user(),
            20,
            [
                'q' => $filters['q'] ?? '',
                'category' => $filters['category'] ?? 'all',
                'status' => $filters['status'] ?? 'all',
                'archived' => $archived,
            ],
        )->withQueryString();

        return view('professional.messages.index', [
            'conversations' => $conversations,
            'search' => trim((string) ($filters['q'] ?? '')),
            'category' => $filters['category'] ?? 'all',
            'status' => $filters['status'] ?? 'all',
            'archived' => $archived,
        ]);
    }

    public function show(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): View {
        $this->authorize('view', $conversation);
        if ($request->filled('cursor') === false) {
            $conversationService->markAsRead($conversation, $request->user());
        }
        $conversation = $conversationService->showForUser($conversation, $request->user());
        $messages = $conversationService->listMessages($conversation, $request->user(), 50);

        return view('professional.messages.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'lastMessageId' => (int) (collect($messages->items())->max('id') ?? 0),
        ]);
    }

    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $this->authorize('send', $conversation);
        $data = $request->validated();

        $conversationService->sendMessage(
            $conversation,
            $request->user(),
            (string) ($data['body'] ?? ''),
            $data['idempotency_key'] ?? null,
            array_values($request->file('attachments', [])),
            isset($data['reply_to_message_id']) ? (int) $data['reply_to_message_id'] : null,
        );

        return back()->with('success', 'Message envoyé.');
    }

    public function updates(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): JsonResponse {
        $filters = $request->validate([
            'after_message_id' => ['required', 'integer', 'min:0'],
            'visible' => ['sometimes', 'boolean'],
        ]);
        $this->authorize('view', $conversation);
        $messages = $conversationService->messagesAfter(
            $conversation,
            $request->user(),
            (int) $filters['after_message_id'],
        );

        if (($filters['visible'] ?? false) && $messages->isNotEmpty()) {
            $conversationService->markAsRead($conversation, $request->user());
        }

        $conversationData = $conversationService->showForUser($conversation, $request->user());

        return response()->json([
            'data' => MessageResource::collection($messages)->resolve($request),
            'meta' => [
                'last_message_id' => (int) ($messages->max('id') ?? $filters['after_message_id']),
                'recipient_last_read_message_id' => $conversationData->recipient_last_read_message_id,
            ],
        ]);
    }

    public function read(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $this->authorize('read', $conversation);
        $conversationService->markAsRead($conversation, $request->user());

        return back()->with('success', 'Conversation marquée comme lue.');
    }

    public function togglePin(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $this->authorize('view', $conversation);
        $pinned = $request->boolean('pinned');
        $conversationService->setPinned($conversation, $request->user(), $pinned);

        return back()->with('success', $pinned ? 'Conversation épinglée.' : 'Conversation désépinglée.');
    }

    public function toggleArchive(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $this->authorize('view', $conversation);
        $archived = $request->boolean('archived');
        $conversationService->setArchived($conversation, $request->user(), $archived);

        return redirect()
            ->route('professional.messages', ['archived' => $archived ? '1' : '0'])
            ->with('success', $archived ? 'Conversation archivée. Son historique est conservé.' : 'Conversation restaurée.');
    }

    public function toggleReadReceipts(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $data = $request->validate(['share_read_receipts' => ['required', 'boolean']]);
        $this->authorize('view', $conversation);
        $enabled = (bool) $data['share_read_receipts'];
        $conversationService->setReadReceipts($conversation, $request->user(), $enabled);

        return back()->with('success', $enabled
            ? 'Les accusés de lecture sont activés pour cette conversation.'
            : 'Les accusés de lecture sont désactivés pour cette conversation.');
    }

    public function block(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $this->authorize('view', $conversation);
        $conversationService->blockConversation($conversation, $request->user());

        return redirect()
            ->route('professional.messages')
            ->with('success', 'Cette conversation est bloquée. Son historique reste conservé.');
    }

    public function reportMessage(
        ReportMessageRequest $request,
        Conversation $conversation,
        Message $message,
    ): RedirectResponse {
        $this->authorize('view', $conversation);
        abort_unless((string) $message->conversation_id === (string) $conversation->getKey(), 404);
        abort_if((string) $message->sender_id === (string) $request->user()->getKey(), 403);

        Report::query()->firstOrCreate(
            [
                'reporter_id' => $request->user()->getKey(),
                'target_type' => 'message',
                'target_id' => $message->getKey(),
                'status' => ReportStatus::PENDING->value,
            ],
            [
                'reason_code' => $request->validated('reason_code'),
                'description' => $request->validated('description'),
                'priority' => ReportPriority::NORMAL->value,
            ],
        );

        return back()->with('success', 'Le signalement a été transmis à l’équipe de modération.');
    }

    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $attachment->loadMissing('message.conversation');
        $this->authorize('view', $attachment->message->conversation);

        abort_unless($attachment->disk === 'local', 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
