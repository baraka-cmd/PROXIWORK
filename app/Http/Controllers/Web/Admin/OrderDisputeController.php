<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderDisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderDispute;
use App\Models\User;
use App\Services\Admin\Dispute\OrderDisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderDisputeController extends Controller
{
    public function __construct(private readonly OrderDisputeService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', OrderDispute::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:open,under_review,awaiting_client,awaiting_professional,mediation,decision_pending,resolved,closed,reopened'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,critical'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        return view('admin.disputes.index', [
            'disputes' => $this->service->paginate($filters),
            'filters' => $filters,
            'overview' => $this->service->overview(),
            'assignees' => User::query()->where('account_status', 'active')->whereHas('roles.permissions', fn ($q) => $q->where('name', 'disputes.manage'))->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function show(OrderDispute $dispute): View
    {
        $this->authorize('view', $dispute);
        return view('admin.disputes.show', [
            'dispute' => $this->service->show($dispute),
            'assignees' => User::query()->where('account_status', 'active')->whereHas('roles.permissions', fn ($q) => $q->where('name', 'disputes.manage'))->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function assign(Request $request, OrderDispute $dispute): RedirectResponse
    {
        $this->authorize('manage', $dispute);
        $data = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);
        $this->service->assign($dispute, User::query()->findOrFail($data['assigned_to']), $request->user(), $request);
        return back()->with('success', 'Le litige a été attribué.');
    }

    public function transition(Request $request, OrderDispute $dispute): RedirectResponse
    {
        $this->authorize('manage', $dispute);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:under_review,awaiting_client,awaiting_professional,mediation,decision_pending,resolved,closed'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        if ($data['status'] === OrderDisputeStatus::RESOLVED->value) {
            abort(422, 'Utilisez le formulaire de décision motivée.');
        }
        $this->service->transition($dispute, OrderDisputeStatus::from($data['status']), $data['reason'], $request->user(), $request);
        return back()->with('success', 'Le statut du litige a été actualisé.');
    }

    public function decide(Request $request, OrderDispute $dispute): RedirectResponse
    {
        $this->authorize('decide', $dispute);
        $data = $request->validate([
            'resolution_code' => ['required', 'string', 'in:client_favor,professional_favor,amicable_agreement,no_breach,financial_review_required,other'],
            'resolution_note' => ['required', 'string', 'min:20', 'max:4000'],
        ]);
        $this->service->decide($dispute, $data['resolution_code'], $data['resolution_note'], $request->user(), $request);
        return back()->with('success', 'La décision motivée a été enregistrée. Toute opération financière éventuelle reste à traiter dans le module financier.');
    }

    public function message(Request $request, OrderDispute $dispute): RedirectResponse
    {
        $this->authorize('message', $dispute);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:10000'],
            'visibility' => ['nullable', 'in:participants,internal'],
        ]);
        $this->service->message($dispute, $request->user(), $data['body'], ($data['visibility'] ?? 'participants') === 'internal', $request);
        return back()->with('success', 'Le message a été ajouté au dossier.');
    }
}
