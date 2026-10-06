<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest\IndexServiceRequestRequest;
use App\Http\Requests\ServiceRequest\RejectServiceRequestRequest;
use App\Http\Requests\ServiceRequest\StoreServiceRequestRequest;
use App\Http\Requests\ServiceRequest\UpdateServiceRequestRequest;
use App\Http\Resources\ServiceRequest\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use App\Services\ServiceRequest\ServiceRequestLifecycleService;
use App\Services\ServiceRequest\ServiceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ServiceRequestController extends Controller
{
    public function __construct(
        private readonly ServiceRequestService $serviceRequestService,
        private readonly ServiceRequestLifecycleService $lifecycleService,
        private readonly AuditLogService $auditLogService,
    )
    {}

    public function clientIndex(IndexServiceRequestRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAnyClient', ServiceRequest::class);

        $requests = $this->serviceRequestService->clientRequests(
            $request->user(),
            $request->integer('per_page', 15),
        );

        return ServiceRequestResource::collection($requests)->additional([
            'message' => 'Vos demandes ont été récupérées avec succès.',
            'meta' => ['scope' => 'client'],
        ]);
    }

    public function professionalIndex(IndexServiceRequestRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAnyProfessional', ServiceRequest::class);

        $requests = $this->serviceRequestService->professionalRequests(
            $request->user(),
            $request->integer('per_page', 15),
        );

        return ServiceRequestResource::collection($requests)->additional([
            'message' => 'Les demandes qui vous sont destinées ont été récupérées avec succès.',
            'meta' => ['scope' => 'professional'],
        ]);
    }

    public function store(StoreServiceRequestRequest $request): JsonResponse
    {
        $serviceRequest = $this->serviceRequestService->create(
            $request->user(),
            $request->validated(),
        );

        $this->auditLogService->record('service_request_created', $serviceRequest, $request->user(), [], $request);

        return (new ServiceRequestResource($serviceRequest))->additional([
            'message' => 'Demande créée en brouillon.',
            'meta' => [],
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $this->authorize('view', $serviceRequest);

        $serviceRequest->load([
            'service',
            'professional.user',
            'address',
            'statusHistories.actor',
        ]);

        return (new ServiceRequestResource($serviceRequest))->additional([
            'message' => 'Demande récupérée avec succès.',
            'meta' => [],
        ]);
    }

    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $this->authorize('update', $serviceRequest);

        $serviceRequest = $this->serviceRequestService->update(
            $serviceRequest,
            $request->validated(),
        );

        $this->auditLogService->record('service_request_updated', $serviceRequest, $request->user(), [], $request);

        return (new ServiceRequestResource($serviceRequest))->additional([
            'message' => 'Demande mise à jour avec succès.',
            'meta' => [],
        ]);
    }

    public function submit(Request $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $this->authorize('submit', $serviceRequest);

        $serviceRequest = $this->lifecycleService->submit($serviceRequest, $request->user());

        $this->auditLogService->record('service_request_submitted', $serviceRequest, $request->user(), [], $request);
        $serviceRequest->professional->user->notify(new AccountActivityNotification(
            'Nouvelle demande de service',
            'Un client vous a envoyé une nouvelle demande pour le service « '.$serviceRequest->service->title.' ».',
            'service_request',
        ));

        return (new ServiceRequestResource($serviceRequest))->additional([
            'message' => 'Demande envoyée au professionnel avec succès.',
            'meta' => [],
        ]);
    }

    public function cancel(Request $request, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $this->authorize('cancel', $serviceRequest);

        $serviceRequest = $this->lifecycleService->cancel($serviceRequest, $request->user());

        $this->auditLogService->record('service_request_cancelled', $serviceRequest, $request->user(), [], $request);
        $serviceRequest->professional->user->notify(new AccountActivityNotification(
            'Demande annulée',
            'Une demande de service qui vous était destinée a été annulée par le client.',
            'service_request',
        ));

        return (new ServiceRequestResource($serviceRequest))->additional([
            'message' => 'Demande annulée avec succès.',
            'meta' => [],
        ]);
    }

    public function reject(
        RejectServiceRequestRequest $request,
        ServiceRequest $serviceRequest,
    ): ServiceRequestResource
    {
        $this->authorize('reject', $serviceRequest);

        $serviceRequest = $this->lifecycleService->rejectByProfessional(
            $serviceRequest,
            $request->user(),
            $request->validated('reason'),
        );

        $this->auditLogService->record('service_request_rejected', $serviceRequest, $request->user(), [
            'reason' => $request->validated('reason'),
        ], $request);

        $serviceRequest->client->notify(new AccountActivityNotification(
            'Demande refusée',
            'Le professionnel a refusé votre demande de service.',
            'service_request',
        ));

        return (new ServiceRequestResource($serviceRequest))->additional([
            'message' => 'Demande refusée avec succès.',
            'meta' => [],
        ]);
    }
}
