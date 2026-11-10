<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Services\Admin\Dispute\OrderDisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderDisputeParticipantController extends Controller
{
    public function __construct(private readonly OrderDisputeService $service) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', 'string', 'in:service_not_delivered,incomplete_work,quality_dispute,late_delivery,terms_not_respected,delivery_disagreement,conduct,other'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
        ]);
        $dispute = $this->service->openForOrder($order, $request->user(), $data['reason_code'], $data['description'], $request);
        return redirect()->route($this->routePrefix($request).'disputes.show', $dispute)
            ->with('success', 'Votre dossier de litige a été créé. Les deux parties et l’administration pourront suivre son traitement.');
    }

    public function show(Request $request, OrderDispute $dispute): View
    {
        Gate::authorize('view', $dispute);
        $dispute = $this->service->show($dispute);
        $prefix = $this->routePrefix($request);
        return view($prefix === 'client.' ? 'client.disputes.show' : 'professional.disputes.show', compact('dispute', 'prefix'));
    }

    public function message(Request $request, OrderDispute $dispute): RedirectResponse
    {
        Gate::authorize('message', $dispute);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:10000']]);
        $this->service->message($dispute, $request->user(), $data['body'], false, $request);
        return back()->with('success', 'Votre message a été ajouté au dossier.');
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('client.*') ? 'client.' : 'professional.';
    }
}
