<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Http\Resources\Quotation\QuotationResource;
use App\Models\Quotation;
use App\Services\Quotation\QuotationNegotiationService;
use App\Notifications\AccountActivityNotification;
use Illuminate\Http\Request;

class QuotationOfferController extends Controller
{
    public function __construct(private readonly QuotationNegotiationService $negotiationService) {}

    public function store(StoreQuotationOfferRequest $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('createOffer', $quotation);

        $updated = $this->negotiationService->counterOffer(
            $quotation,
            $request->user(),
            $request->validated(),
        );

        $updated->load('serviceRequest.client', 'serviceRequest.professional.user');
        $recipient = $updated->serviceRequest->client_id === $request->user()->getKey()
            ? $updated->serviceRequest->professional->user
            : $updated->serviceRequest->client;

        $recipient->notify(new AccountActivityNotification(
            'Nouvelle contre-proposition',
            'Une nouvelle proposition commerciale est disponible dans votre négociation.',
            'quotation',
        ));

        return new QuotationResource($updated);
    }
}
