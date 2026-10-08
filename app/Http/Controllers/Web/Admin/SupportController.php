<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Support\AdminSupportAssignRequest;
use App\Http\Requests\Admin\Support\AdminSupportIndexRequest;
use App\Http\Requests\Admin\Support\AdminSupportMessageRequest;
use App\Http\Requests\Admin\Support\AdminSupportUpdateRequest;
use App\Models\SupportTicket;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\User;
use App\Services\Admin\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(
        private readonly SupportTicketService $supportService,
    ) {}

    public function index(AdminSupportIndexRequest $request): View
    {
        return view('admin.support.index', [
            'tickets' => $this->supportService->paginate($request->validated()),
            'filters' => $request->validated(),
            'assignees' => $this->supportService->assignableUsers(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        return view('admin.support.show', [
            'ticket' => $this->supportService->show($ticket),
            'assignees' => $this->supportService->assignableUsers(),
        ]);
    }

    public function assign(AdminSupportAssignRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $assignee = User::query()->findOrFail($request->validated('assigned_to'));
        $this->supportService->assign($ticket, $assignee, $request->user(), $request);

        return back()->with('success', 'Le ticket a été assigné.');
    }

    public function update(AdminSupportUpdateRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        if ($request->validated('priority') !== null) {
            $this->supportService->updatePriority(
                $request->user(),
                $ticket,
                SupportTicketPriority::from($request->validated('priority')),
                $request,
            );
        }

        if ($request->validated('status') !== null) {
            $this->supportService->transition(
                $request->user(),
                $ticket,
                SupportTicketStatus::from($request->validated('status')),
                $request,
            );
        }

        return back()->with('success', 'Le ticket a été mis à jour.');
    }

    public function message(AdminSupportMessageRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('message', $ticket);

        $this->supportService->message(
            $request->user(),
            $ticket,
            $request->validated('body'),
            $request,
        );

        return back()->with('success', 'La réponse a été envoyée.');
    }

    public function resolve(SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $this->supportService->transition(request()->user(), $ticket, SupportTicketStatus::RESOLVED, request());

        return back()->with('success', 'Le ticket a été marqué comme résolu.');
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $this->supportService->transition(request()->user(), $ticket, SupportTicketStatus::CLOSED, request());

        return back()->with('success', 'Le ticket a été clôturé.');
    }
}
