<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Http\Resources\Quotation\QuotationResource;
use App\Models\Quotation;
use App\Services\Quotation\QuotationNegotiationService;
use Illuminate\Http\Request;

class QuotationOfferController extends Controller
{
    public function __construct(private readonly QuotationNegotiationService $negotiationService) {}

    public function store(StoreQuotationOfferRequest $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('createOffer', $quotation);

        return new QuotationResource($this->negotiationService->counterOffer(
            $quotation,
            $request->user(),
            $request->validated(),
        ));
    }
}
