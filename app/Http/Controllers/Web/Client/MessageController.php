<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\ReportMessageRequest;
use App\Http\Requests\Messaging\StoreConversationRequest;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Http\Resources\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Report;
use App\Models\ServiceRequest;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::in(['all', 'requests', 'orders', 'general'])],
            'status' => ['nullable', 'string', Rule::in(['all', 'unread'])],
            'archived' => ['nullable', 'string', Rule::in(['0', '1'])],
        ]);

        $archived = ($filters['archived'] ?? '0') === '1';
        $conversations = $this->conversationService->listForUser(
            $request->user(),
            20,
            [
                'q' => $filters['q'] ?? '',
                'category' => $filters['category'] ?? 'all',
                'status' => $filters['status'] ?? 'all',
                'archived' => $archived,
            ],
        )->withQueryString();

        return view('client.messages.index', [
            'conversations' => $conversations,
            'selected' => $request->integer('conversation'),
            'search' => trim((string) ($filters['q'] ?? '')),
            'category' => $filters['category'] ?? 'all',
            'status' => $filters['status'] ?? 'all',
            'archived' => $archived,
        ]);
    }

    public function start(
        StoreConversationRequest $request,
        ProfessionalProfile $professionalProfile,
    ): RedirectResponse {
        $conversation = $this->conversationService->findOrCreateForClient(
            client: $request->user(),
            professional: $professionalProfile,
            serviceRequest: $request->filled('service_request_id')
                ? ServiceRequest::query()->findOrFail($request->integer('service_request_id'))
                : null,
            order: $request->filled('order_id')
                ? Order::query()->findOrFail($request->integer('order_id'))
                : null,
        );

        return redirect()->route('client.messages.show', $conversation);
    }

    public function show(Request $request, int $conversation): View
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);

        // Only the newest page is considered read; loading historical pages must not clear unread state.
        if ($request->filled('cursor') === false) {
            $this->conversationService->markAsRead($model, $request->user());
        }
        $conversationData = $this->conversationService->showForUser($model, $request->user());
        $messages = $this->conversationService->listMessages($model, $request->user(), 50);
        $lastMessageId = collect($messages->items())->max('id') ?? 0;

        return view('client.messages.show', [
            'conversation' => $conversationData,
            'messages' => $messages,
            'lastMessageId' => (int) $lastMessageId,
        ]);
    }

    public function store(StoreMessageRequest $request, int $conversation): RedirectResponse
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('send', $model);
        $data = $request->validated();

        $this->conversationService->sendMessage(
            $model,
            $request->user(),
            (string) ($data['body'] ?? ''),
            $data['idempotency_key'] ?? null,
            array_values($request->file('attachments', [])),
            isset($data['reply_to_message_id']) ? (int) $data['reply_to_message_id'] : null,
        );

        return redirect()
            ->route('client.messages.show', $model)
            ->with('success', 'Message envoyé.');
    }

    public function updates(Request $request, int $conversation): JsonResponse
    {
        $filters = $request->validate([
            'after_message_id' => ['required', 'integer', 'min:0'],
            'visible' => ['sometimes', 'boolean'],
        ]);
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);

        $messages = $this->conversationService->messagesAfter(
            $model,
            $request->user(),
            (int) $filters['after_message_id'],
        );

        if (($filters['visible'] ?? false) && $messages->isNotEmpty()) {
            $this->conversationService->markAsRead($model, $request->user());
        }

        $conversationData = $this->conversationService->showForUser($model, $request->user());

        return response()->json([
            'data' => MessageResource::collection($messages)->resolve($request),
            'meta' => [
                'last_message_id' => (int) ($messages->max('id') ?? $filters['after_message_id']),
                'recipient_last_read_message_id' => $conversationData->recipient_last_read_message_id,
            ],
        ]);
    }

    public function read(Request $request, int $conversation): RedirectResponse
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('read', $model);
        $this->conversationService->markAsRead($model, $request->user());

        return back()->with('success', 'Conversation marquée comme lue.');
    }

    public function togglePin(Request $request, int $conversation): RedirectResponse
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);
        $pinned = $request->boolean('pinned');
        $this->conversationService->setPinned($model, $request->user(), $pinned);

        return back()->with('success', $pinned ? 'Conversation épinglée.' : 'Conversation désépinglée.');
    }

    public function toggleArchive(Request $request, int $conversation): RedirectResponse
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);
        $archived = $request->boolean('archived');
        $this->conversationService->setArchived($model, $request->user(), $archived);

        return redirect()
            ->route('client.messages.index', ['archived' => $archived ? '1' : '0'])
            ->with('success', $archived ? 'Conversation archivée. Son historique est conservé.' : 'Conversation restaurée.');
    }

    public function toggleReadReceipts(Request $request, int $conversation): RedirectResponse
    {
        $data = $request->validate(['share_read_receipts' => ['required', 'boolean']]);
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);
        $enabled = (bool) $data['share_read_receipts'];
        $this->conversationService->setReadReceipts($model, $request->user(), $enabled);

        return back()->with('success', $enabled
            ? 'Les accusés de lecture sont activés pour cette conversation.'
            : 'Les accusés de lecture sont désactivés pour cette conversation.');
    }

    public function block(Request $request, int $conversation): RedirectResponse
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);
        $this->conversationService->blockConversation($model, $request->user());

        return redirect()
            ->route('client.messages.index')
            ->with('success', 'Cette conversation est bloquée. Son historique reste conservé.');
    }

    public function reportMessage(
        ReportMessageRequest $request,
        int $conversation,
        Message $message,
    ): RedirectResponse {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);
        abort_unless((string) $message->conversation_id === (string) $model->getKey(), 404);
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
