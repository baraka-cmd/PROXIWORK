<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Resources\Quotation\QuotationResource;
use App\Models\Quotation;
use App\Models\ServiceRequest;
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
        $this->notificationService->quotationCreated($serviceRequest->client);

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
        $this->notificationService->quotationAccepted($accepted->serviceRequest->professional->user);

        return new QuotationResource($accepted);
    }

    public function reject(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('reject', $quotation);

        $rejected = $this->quotationService->reject($quotation, $request->user());
        $rejected->load('serviceRequest.professional.user');
        $this->notificationService->quotationRejected($rejected->serviceRequest->professional->user);

        return new QuotationResource($rejected);
    }
}
