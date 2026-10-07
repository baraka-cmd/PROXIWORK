<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Services\ServiceRequest\ServiceRequestLifecycleService;
use App\Services\ServiceRequest\ServiceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function __construct(
        private readonly ServiceRequestService $serviceRequestService,
        private readonly ServiceRequestLifecycleService $lifecycleService,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $statusEnum = is_string($status) ? ServiceRequestStatus::tryFrom($status) : null;

        $requests = $this->serviceRequestService->clientRequests(
            $request->user(),
            12,
            $statusEnum,
        );

        return view('client.requests.index', [
            'requests' => $requests,
            'selectedStatus' => is_string($status) ? $status : null,
        ]);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): View
    {
        $this->authorize('view', $serviceRequest);

        $serviceRequest->load([
            'service',
            'professional.user',
            'address',
            'statusHistories.actor',
            'quotation.currentOffer',
            'quotation.acceptedOffer',
            'quotation.offers',
            'quotation.events',
            'order',
        ]);

        return view('client.requests.show', compact('serviceRequest'));
    }

    public function submit(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('submit', $serviceRequest);
        $this->lifecycleService->submit($serviceRequest, $request->user());

        return back()->with('success', 'Votre demande a été envoyée au professionnel.');
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('cancel', $serviceRequest);
        $this->lifecycleService->cancel($serviceRequest, $request->user());

        return back()->with('success', 'Votre demande a été annulée.');
    }
}
