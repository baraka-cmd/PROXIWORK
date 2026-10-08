<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request, ConversationService $conversationService): View
    {
        $conversations = $conversationService->listForUser($request->user(), 20);

        return view('professional.messages.index', [
            'conversations' => $conversations,
        ]);
    }

    public function show(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): View {
        $this->authorize('view', $conversation);

        $conversationService->markAsRead($conversation, $request->user());

        $conversation = $conversationService->showForUser($conversation, $request->user());
        $messages = $conversationService->listMessages($conversation, $request->user(), 50);

        return view('professional.messages.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $this->authorize('send', $conversation);

        $conversationService->sendMessage(
            $conversation,
            $request->user(),
            (string) $request->string('body'),
        );

        return back()->with('success', 'Message envoyé.');
    }

    public function read(
        Request $request,
        Conversation $conversation,
        ConversationService $conversationService,
    ): RedirectResponse {
        $this->authorize('read', $conversation);

        $conversationService->markAsRead($conversation, $request->user());

        return back();
    }
}
