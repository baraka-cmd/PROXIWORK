<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\ReportMessageRequest;
use App\Http\Requests\Messaging\StoreConversationRequest;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Http\Resources\Messaging\ConversationResource;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {
        // Dependencies are injected by Laravel.
    }

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'string', Rule::in(['all', 'requests', 'orders', 'general'])],
            'status' => ['sometimes', 'string', Rule::in(['all', 'unread'])],
            'archived' => ['sometimes', 'boolean'],
        ]);

        $conversations = $this->conversationService->listForUser(
            $request->user(),
            (int) ($filters['per_page'] ?? 20),
            [
                'q' => $filters['q'] ?? '',
                'category' => $filters['category'] ?? 'all',
                'status' => $filters['status'] ?? 'all',
                'archived' => (bool) ($filters['archived'] ?? false),
            ],
        );

        return response()->json([
            'data' => ConversationResource::collection($conversations->items()),
            'meta' => [
                'per_page' => $conversations->perPage(),
                'next_cursor' => $conversations->nextCursor()?->encode(),
                'previous_cursor' => $conversations->previousCursor()?->encode(),
            ],
        ]);
    }

    public function storeForProfessional(
        StoreConversationRequest $request,
        ProfessionalProfile $professionalProfile,
    ): JsonResponse {
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

        return response()->json([
            'message' => 'Conversation prête.',
            'data' => new ConversationResource($conversation),
            'meta' => [],
        ], 201);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);

        return new ConversationResource(
            $this->conversationService->showForUser($conversation, $request->user())
        );
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $this->conversationService->listMessages(
            $conversation,
            $request->user(),
            (int) $request->integer('per_page', 50),
        );

        return response()->json([
            'data' => MessageResource::collection($messages->items()),
            'meta' => [
                'per_page' => $messages->perPage(),
                'next_cursor' => $messages->nextCursor()?->encode(),
                'previous_cursor' => $messages->previousCursor()?->encode(),
            ],
        ]);
    }

    public function storeMessage(
        Request $request,
        StoreMessageRequest $messageRequest,
        Conversation $conversation,
    ): JsonResponse {
        $this->authorize('send', $conversation);

        $message = $this->conversationService->sendMessage(
            $conversation,
            $request->user(),
            (string) ($messageRequest->validated('body') ?? ''),
            $messageRequest->validated('idempotency_key'),
            array_values($messageRequest->file('attachments', [])),
            $messageRequest->validated('reply_to_message_id') === null
                ? null
                : (int) $messageRequest->validated('reply_to_message_id'),
        );

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => new MessageResource($message),
            'meta' => [],
        ], 201);
    }

    public function setReadReceipts(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate(['share_read_receipts' => ['required', 'boolean']]);
        $this->authorize('view', $conversation);
        $enabled = (bool) $data['share_read_receipts'];
        $this->conversationService->setReadReceipts($conversation, $request->user(), $enabled);

        return response()->json([
            'message' => $enabled ? 'Accusés de lecture activés.' : 'Accusés de lecture désactivés.',
            'data' => ['conversation_id' => $conversation->getKey(), 'share_read_receipts' => $enabled],
            'meta' => [],
        ]);
    }

    public function pin(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        $this->authorize('view', $conversation);
        $this->conversationService->setPinned($conversation, $request->user(), (bool) $data['pinned']);

        return response()->json([
            'message' => $data['pinned'] ? 'Conversation épinglée.' : 'Conversation désépinglée.',
            'data' => ['conversation_id' => $conversation->getKey(), 'pinned' => (bool) $data['pinned']],
            'meta' => [],
        ]);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        $this->authorize('view', $conversation);
        $this->conversationService->setArchived($conversation, $request->user(), (bool) $data['archived']);

        return response()->json([
            'message' => $data['archived'] ? 'Conversation archivée.' : 'Conversation restaurée.',
            'data' => ['conversation_id' => $conversation->getKey(), 'archived' => (bool) $data['archived']],
            'meta' => [],
        ]);
    }

    public function block(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->conversationService->blockConversation($conversation, $request->user());

        return response()->json([
            'message' => 'Conversation bloquée. Son historique reste conservé.',
            'data' => ['conversation_id' => $conversation->getKey(), 'status' => 'blocked'],
            'meta' => [],
        ]);
    }

    public function reportMessage(
        ReportMessageRequest $request,
        Conversation $conversation,
        Message $message,
    ): JsonResponse {
        $this->authorize('view', $conversation);
        abort_unless((string) $message->conversation_id === (string) $conversation->getKey(), 404);
        abort_if((string) $message->sender_id === (string) $request->user()->getKey(), 403);

        $report = Report::query()->firstOrCreate(
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

        return response()->json([
            'message' => $report->wasRecentlyCreated
                ? 'Signalement transmis à l’équipe de modération.'
                : 'Ce message a déjà été signalé et reste en cours de traitement.',
            'data' => ['report_id' => $report->getKey(), 'status' => $report->status->value],
            'meta' => [],
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    public function downloadAttachment(
        Request $request,
        Conversation $conversation,
        MessageAttachment $attachment,
    ) {
        $this->authorize('view', $conversation);
        $attachment->loadMissing('message');

        abort_unless((string) $attachment->message->conversation_id === (string) $conversation->getKey(), 404);
        abort_unless($attachment->disk === 'local', 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('read', $conversation);

        $this->conversationService->markAsRead($conversation, $request->user());

        return response()->json([
            'message' => 'Conversation marquée comme lue.',
            'data' => [
                'conversation_id' => $conversation->getKey(),
            ],
            'meta' => [],
        ]);
    }
}
