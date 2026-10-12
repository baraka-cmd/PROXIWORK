<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Http\Resources\Quotation\QuotationResource;
use App\Models\Quotation;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use App\Services\Quotation\QuotationNegotiationService;

class QuotationOfferController extends Controller
{
    public function __construct(
        private readonly QuotationNegotiationService $negotiationService,
        private readonly TransactionalNotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function store(StoreQuotationOfferRequest $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('createOffer', $quotation);

        $updated = $this->negotiationService->counterOffer(
            $quotation,
            $request->user(),
            $request->validated(),
        );

        $updated->load('serviceRequest.client', 'serviceRequest.professional.user');
        $clientInitiated = $updated->serviceRequest->client_id === $request->user()->getKey();
        $recipient = $clientInitiated
            ? $updated->serviceRequest->professional->user
            : $updated->serviceRequest->client;
        $recipientUrl = $clientInitiated
            ? route('professional.quotes.show', $updated, true)
            : route('client.quotes.show', $updated, true);

        $this->auditLogService->record(
            'quotation_counter_offer_created',
            $updated,
            $request->user(),
            ['service_request_id' => $updated->service_request_id],
            $request,
        );
        $this->notificationService->counterOfferReceived($recipient, $recipientUrl);

        return new QuotationResource($updated);
    }
}
