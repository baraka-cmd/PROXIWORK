<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Resources\Quotation\QuotationResource;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use App\Services\Quotation\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly TransactionalNotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function show(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('view', $quotation);

        $quotation->load(['serviceRequest.service', 'currentOffer', 'acceptedOffer', 'offers', 'events']);

        return new QuotationResource($quotation);
    }

    public function store(StoreQuotationRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('create', [Quotation::class, $serviceRequest]);

        $quotation = $this->quotationService->create(
            $serviceRequest,
            $request->user(),
            $request->validated(),
        );

        $serviceRequest->load('client');
        $this->auditLogService->record(
            'quotation_created',
            $quotation,
            $request->user(),
            ['service_request_id' => $serviceRequest->getKey()],
            $request,
        );
        $this->notificationService->quotationCreated($serviceRequest->client, route('client.quotes.show', $quotation, true));

        return (new QuotationResource($quotation))->additional([
            'message' => 'Devis créé et envoyé au client avec succès.',
            'meta' => [],
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function accept(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('accept', $quotation);

        $accepted = $this->quotationService->accept($quotation, $request->user());
        $accepted->load('serviceRequest.professional.user');
        $this->auditLogService->record(
            'quotation_accepted',
            $accepted,
            $request->user(),
            ['service_request_id' => $accepted->service_request_id],
            $request,
        );
        $this->notificationService->quotationAccepted($accepted->serviceRequest->professional->user, route('professional.quotes.show', $accepted, true));

        return new QuotationResource($accepted);
    }

    public function reject(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('reject', $quotation);

        $rejected = $this->quotationService->reject($quotation, $request->user());
        $rejected->load('serviceRequest.professional.user');
        $this->auditLogService->record(
            'quotation_rejected',
            $rejected,
            $request->user(),
            ['service_request_id' => $rejected->service_request_id],
            $request,
        );
        $this->notificationService->quotationRejected($rejected->serviceRequest->professional->user, route('professional.quotes.show', $rejected, true));

        return new QuotationResource($rejected);
    }
}
