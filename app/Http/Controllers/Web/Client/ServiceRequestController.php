<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\IndexServiceRequestRequest;
use App\Http\Requests\ServiceRequest\SetServiceRequestAddressRequest;
use App\Http\Requests\ServiceRequest\StoreServiceRequestRequest;
use App\Http\Requests\ServiceRequest\UpdateServiceRequestRequest;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\Audit\AuditLogService;
use App\Services\ServiceRequest\ServiceRequestLifecycleService;
use App\Services\ServiceRequest\ServiceRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function __construct(
        private readonly ServiceRequestService $serviceRequestService,
        private readonly ServiceRequestLifecycleService $lifecycleService,
        private readonly AuditLogService $auditLogService,
    ) {
        // Dependencies are promoted constructor properties.
    }

    public function index(IndexServiceRequestRequest $request): View
    {
        $filters = $request->filters();
        $client = $request->user();

        $requests = $this->serviceRequestService->clientRequests(
            $client,
            $request->integer('per_page', 12),
            null,
            $filters,
        );

        $categories = Category::query()
            ->whereHas('services.serviceRequests', fn (Builder $query) => $query
                ->where('client_id', $client->getKey()))
            ->orderBy('name')
            ->get(['id', 'name']);

        $professionals = ProfessionalProfile::query()
            ->whereHas('serviceRequests', fn (Builder $query) => $query
                ->where('client_id', $client->getKey()))
            ->with('user:id,name')
            ->orderBy('professional_title')
            ->get(['id', 'user_id', 'professional_title']);

        return view('client.requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'selectedStatus' => $filters['status'] ?? '',
            'stats' => $this->serviceRequestService->clientRequestStats($client),
            'categories' => $categories,
            'professionals' => $professionals,
        ]);
    }

    public function create(Request $request): View
    {
        $service = Service::query()
            ->requestable()
            ->with(['professionalProfile.user', 'category', 'skills'])
            ->findOrFail($request->integer('service'));

        $addresses = $request->user()->addresses()->latest('is_default')->latest('id')->get();

        $idempotencyKey = (string) Str::uuid();

        return view('client.requests.create', compact('service', 'addresses', 'idempotencyKey'));
    }

    public function store(StoreServiceRequestRequest $request): RedirectResponse
    {
        $serviceRequest = $this->serviceRequestService->create($request->user(), $request->validated());

        if ($serviceRequest->getAttribute('_idempotent_replay') !== true) {
            $this->auditLogService->record(
                'service_request_created',
                $serviceRequest,
                $request->user(),
                ['status' => $serviceRequest->status->value],
                $request,
            );
        }

        $message = $serviceRequest->status->value === 'draft'
            ? 'Votre demande est enregistrée comme brouillon. Vérifiez-la puis envoyez-la au professionnel.'
            : 'Une demande existante a été retrouvée. Consultez son état actuel avant de continuer.';

        return redirect()
            ->route('client.requests.show', $serviceRequest)
            ->with('success', $message);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): View
    {
        $this->authorize('view', $serviceRequest);

        $serviceRequest->load([
            'service.category',
            'professional.user',
            'address',
            'addressSnapshot',
            'statusHistories.actor',
            'quotation.currentOffer',
            'quotation.acceptedOffer',
            'quotation.offers',
            'quotation.events',
            'order',
            'attachments.uploader',
        ]);

        $conversation = Conversation::query()
            ->where('client_id', $request->user()->getKey())
            ->where('professional_id', $serviceRequest->professional_id)
            ->first();

        if ($conversation !== null && ! Gate::allows('view', $conversation)) {
            $conversation = null;
        }

        $addresses = in_array($serviceRequest->status->value, ['requested', 'quoted'], true)
            ? $request->user()->addresses()->latest('is_default')->latest('id')->get()
            : collect();

        return view('client.requests.show', compact('serviceRequest', 'conversation', 'addresses'));
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
        $serviceRequest = $this->serviceRequestService->update($serviceRequest, $request->validated());

        $this->auditLogService->record(
            'service_request_updated',
            $serviceRequest,
            $request->user(),
            ['status' => $serviceRequest->status->value, 'changed_fields' => array_keys($request->validated())],
            $request,
        );

        return redirect()
            ->route('client.requests.show', $serviceRequest)
            ->with('success', 'Votre brouillon a été mis à jour.');
    }

    public function submit(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('submit', $serviceRequest);
        $serviceRequest = $this->lifecycleService->submit($serviceRequest, $request->user());
        $serviceRequest->loadMissing(['professional.user', 'service']);

        $this->auditLogService->record(
            'service_request_submitted',
            $serviceRequest,
            $request->user(),
            ['status' => $serviceRequest->status->value],
            $request,
        );

        return back()->with('success', 'Votre demande a été envoyée au professionnel.');
    }

    public function setAddress(
        SetServiceRequestAddressRequest $request,
        ServiceRequest $serviceRequest,
    ): RedirectResponse {
        $this->authorize('setAddress', $serviceRequest);

        $serviceRequest = $this->serviceRequestService->setAddress(
            $serviceRequest,
            $request->user(),
            (int) $request->validated('address_id'),
        );

        $this->auditLogService->record(
            'service_request_address_updated',
            $serviceRequest,
            $request->user(),
            ['address_id' => $serviceRequest->address_id],
            $request,
        );

        return redirect()
            ->route('client.requests.show', $serviceRequest)
            ->with('success', 'Le lieu d’exécution a été enregistré.');
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('cancel', $serviceRequest);
        $serviceRequest = $this->lifecycleService->cancel($serviceRequest, $request->user());
        $serviceRequest->loadMissing('professional.user');

        $this->auditLogService->record(
            'service_request_cancelled',
            $serviceRequest,
            $request->user(),
            ['status' => $serviceRequest->status->value],
            $request,
        );

        return back()->with('success', 'Votre demande a été annulée.');
    }
}
