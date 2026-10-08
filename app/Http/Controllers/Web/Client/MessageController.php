<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {
        // Dependencies are injected only.
    }

    public function index(Request $request)
    {
        $conversations = $this->conversationService->listForUser($request->user(), 20);
        $selected = $request->integer('conversation');

        return view('client.messages.index', compact('conversations', 'selected'));
    }

    public function show(Request $request, int $conversation)
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('view', $model);

        $conversationData = $this->conversationService->showForUser($model, $request->user());
        $messages = $this->conversationService->listMessages($model, $request->user(), 50);

        return view('client.messages.show', [
            'conversation' => $conversationData,
            'messages' => $messages,
        ]);
    }

    public function store(StoreMessageRequest $request, int $conversation)
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('send', $model);

        $this->conversationService->sendMessage(
            $model,
            $request->user(),
            $request->validated('body'),
        );

        return redirect()
            ->route('client.messages.show', $model)
            ->with('success', 'Message envoyé.');
    }

    public function read(Request $request, int $conversation)
    {
        $model = Conversation::query()->findOrFail($conversation);
        $this->authorize('read', $model);
        $this->conversationService->markAsRead($model, $request->user());

        return back();
    }
}