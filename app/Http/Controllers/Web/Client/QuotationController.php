<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Models\Quotation;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use App\Services\Quotation\QuotationNegotiationService;
use App\Services\Quotation\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly QuotationNegotiationService $negotiationService,
        private readonly AuditLogService $auditLogService,
        private readonly TransactionalNotificationService $notificationService,
    ) {}

    public function index(Request $request): View
    {
        $quotes = Quotation::query()
            ->whereHas('serviceRequest', fn ($query) => $query->where('client_id', $request->user()->getKey()))
            ->with([
                'serviceRequest.service',
                'serviceRequest.professional.user',
                'currentOffer',
            ])
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('client.quotes.index', compact('quotes'));
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        $quotation->load([
            'serviceRequest.service',
            'serviceRequest.professional.user',
            'serviceRequest.address',
            'currentOffer',
            'acceptedOffer',
            'offers.creator',
            'events',
        ]);

        return view('client.quotes.show', compact('quotation'));
    }

    public function accept(Request $request, Quotation $quotation): RedirectResponse
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
        $this->notificationService->quotationAccepted($accepted->serviceRequest->professional->user);

        return back()->with('success', 'Devis accepté. La commande a été créée.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
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
        $this->notificationService->quotationRejected($rejected->serviceRequest->professional->user);

        return back()->with('success', 'Devis refusé.');
    }

    public function counterOffer(
        StoreQuotationOfferRequest $request,
        Quotation $quotation,
    ): RedirectResponse {
        $this->authorize('createOffer', $quotation);
        $counterOffer = $this->negotiationService->counterOffer(
            $quotation,
            $request->user(),
            $request->validated(),
        );
        $counterOffer->load('serviceRequest.professional.user');

        $this->auditLogService->record(
            'quotation_counter_offer_created',
            $counterOffer,
            $request->user(),
            ['service_request_id' => $counterOffer->service_request_id],
            $request,
        );
        $this->notificationService->counterOfferReceived($counterOffer->serviceRequest->professional->user);

        return back()->with('success', 'Votre contre-proposition a été envoyée.');
    }
}
