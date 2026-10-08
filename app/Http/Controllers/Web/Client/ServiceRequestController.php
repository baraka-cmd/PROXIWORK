<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\StoreServiceRequestRequest;
use App\Http\Requests\ServiceRequest\UpdateServiceRequestRequest;
use App\Models\Service;
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

        return view('client.requests.index', [
            'requests' => $this->serviceRequestService->clientRequests($request->user(), 12, $statusEnum),
            'selectedStatus' => is_string($status) ? $status : null,
        ]);
    }

    public function create(Request $request): View
    {
        $service = Service::query()
            ->with(['professionalProfile.user', 'category', 'skills'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->findOrFail($request->integer('service'));

        $addresses = $request->user()->addresses()->latest('is_default')->latest('id')->get();

        return view('client.requests.create', compact('service', 'addresses'));
    }

    public function store(StoreServiceRequestRequest $request): RedirectResponse
    {
        $serviceRequest = $this->serviceRequestService->create($request->user(), $request->validated());

        return redirect()
            ->route('client.requests.show', $serviceRequest)
            ->with('success', 'Votre demande a été enregistrée comme brouillon. Vérifiez-la puis envoyez-la au professionnel.');
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

    public function edit(Request $request, ServiceRequest $serviceRequest): View
    {
        $this->authorize('update', $serviceRequest);

        $serviceRequest->load(['service', 'professional.user', 'address']);
        $addresses = $request->user()->addresses()->latest('is_default')->latest('id')->get();

        return view('client.requests.edit', compact('serviceRequest', 'addresses'));
    }

    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('update', $serviceRequest);
        $this->serviceRequestService->update($serviceRequest, $request->validated());

        return redirect()
            ->route('client.requests.show', $serviceRequest)
            ->with('success', 'Votre brouillon a été mis à jour.');
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
