<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Resources\Quotation\QuotationResource;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Quotation\QuotationNegotiationService;
use App\Services\Quotation\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly QuotationNegotiationService $negotiationService,
        private readonly AuditLogService $auditLogService,
    ) {
        // Dependencies are promoted constructor properties.
    }

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

        return new QuotationResource($accepted);
    }

    public function acceptClientOffer(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('acceptClientOffer', $quotation);

        $accepted = $this->quotationService->acceptClientOffer($quotation, $request->user());
        $accepted->load(['serviceRequest.client', 'order']);

        $this->auditLogService->record(
            'quotation_client_counter_offer_accepted',
            $accepted,
            $request->user(),
            [
                'service_request_id' => $accepted->service_request_id,
                'accepted_offer_id' => $accepted->accepted_offer_id,
                'order_id' => $accepted->order?->getKey(),
            ],
            $request,
        );

        return new QuotationResource($accepted);
    }

    public function rejectClientOffer(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('rejectClientOffer', $quotation);

        $rejected = $this->quotationService->rejectClientOffer($quotation, $request->user());
        $rejected->load('serviceRequest.client');

        $this->auditLogService->record(
            'quotation_client_counter_offer_rejected',
            $rejected,
            $request->user(),
            ['service_request_id' => $rejected->service_request_id, 'offer_id' => $rejected->current_offer_id],
            $request,
        );

        return new QuotationResource($rejected);
    }

    public function renew(StoreQuotationOfferRequest $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('renew', $quotation);

        $renewed = $this->negotiationService->renewExpiredQuotation(
            $quotation,
            $request->user(),
            $request->validated(),
        );
        $renewed->load('serviceRequest.client');

        $this->auditLogService->record(
            'quotation_renewed',
            $renewed,
            $request->user(),
            [
                'service_request_id' => $renewed->service_request_id,
                'offer_id' => $renewed->current_offer_id,
            ],
            $request,
        );

        return (new QuotationResource($renewed))->additional([
            'message' => 'Le devis expiré a été renouvelé avec une nouvelle version.',
            'meta' => [],
        ]);
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

        return new QuotationResource($rejected);
    }
}
