<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreConversationRequest;
use App\Http\Resources\Messaging\ConversationResource;
use App\Http\Resources\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\ProfessionalProfile;
use App\Models\ServiceRequest;
use App\Models\Order;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationService->listForUser(
            $request->user(),
            (int) $request->integer('per_page', 20),
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
            $messageRequest->validated('body'),
        );

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => new MessageResource($message),
            'meta' => [],
        ], 201);
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
