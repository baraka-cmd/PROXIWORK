<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Support;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\AssignSupportTicketRequest;
use App\Http\Requests\Support\StoreSupportTicketRequest;
use App\Http\Requests\Support\StoreTicketMessageRequest;
use App\Http\Requests\Support\TransitionSupportTicketRequest;
use App\Http\Resources\Support\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Admin\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(private SupportTicketService $service)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = SupportTicket::query()
            ->with(['user:id,name,email', 'assignee:id,name'])
            ->when(
                !$user->hasPermissionTo('support.manage'),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
            )
            ->latest('id');

        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        return SupportTicketResource::collection(
            $query->paginate($perPage)->withQueryString()
        )->additional([
            'message' => 'Tickets récupérés avec succès.',
            'meta' => [],
        ]);
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $this->authorize('create', SupportTicket::class);

        $data = $request->validated();
        $data['priority'] ??= 'normal';

        $ticket = $this->service->create($request->user(), $data, $request);

        return (new SupportTicketResource($ticket))->additional([
            'message' => 'Ticket créé avec succès.',
            'meta' => [],
        ])->response()->setStatusCode(201);
    }

    public function show(Request $request, SupportTicket $ticket): SupportTicketResource
    {
        $this->authorize('view', $ticket);

        return new SupportTicketResource(
            $ticket->load(['user', 'assignee', 'messages.sender'])
        );
    }

    public function message(
        StoreTicketMessageRequest $request,
        SupportTicket $ticket,
    ): SupportTicketResource {
        $this->authorize('message', $ticket);

        $this->service->message(
            $request->user(),
            $ticket,
            $request->string('body')->toString(),
            $request,
        );

        return new SupportTicketResource(
            $ticket->fresh(['user', 'assignee', 'messages.sender'])
        );
    }

    public function transition(
        TransitionSupportTicketRequest $request,
        SupportTicket $ticket,
    ): SupportTicketResource {
        $this->authorize('update', $ticket);

        $result = $this->service->transition(
            $request->user(),
            $ticket,
            SupportTicketStatus::from($request->string('status')->toString()),
            $request,
        );

        return new SupportTicketResource($result);
    }

    public function assign(
        AssignSupportTicketRequest $request,
        SupportTicket $ticket,
    ): SupportTicketResource {
        $this->authorize('update', $ticket);

        $result = $this->service->assign(
            $request->user(),
            $ticket,
            User::findOrFail($request->integer('assigned_to')),
            $request,
        );

        return new SupportTicketResource($result);
    }
}
