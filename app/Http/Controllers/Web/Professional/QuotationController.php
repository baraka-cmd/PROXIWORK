<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Models\Quotation;
use App\Models\ServiceRequest;
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
        $quotations = Quotation::query()
            ->whereHas('serviceRequest.professional', fn ($query) => $query->where('user_id', $request->user()->getKey()))
            ->with(['serviceRequest.service', 'serviceRequest.client', 'currentOffer'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('professional.quotes.index', compact('quotations'));
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        abort_unless(
            $quotation->serviceRequest()->whereHas('professional', fn ($query) => $query->where('user_id', auth()->id()))->exists(),
            404,
        );

        $quotation->load([
            'serviceRequest.service',
            'serviceRequest.client',
            'currentOffer.creator',
            'acceptedOffer.creator',
            'offers.creator',
            'events.actor',
            'order',
        ]);

        return view('professional.quotes.show', compact('quotation'));
    }

    public function create(ServiceRequest $serviceRequest): View
    {
        $this->authorize('create', [Quotation::class, $serviceRequest]);

        abort_unless($serviceRequest->status->value === 'requested' && ! $serviceRequest->quotation()->exists(), 404);

        $serviceRequest->load(['service', 'client']);

        return view('professional.quotes.create', compact('serviceRequest'));
    }

    public function store(StoreQuotationRequest $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorize('create', [Quotation::class, $serviceRequest]);

        $quotation = $this->quotationService->create($serviceRequest, $request->user(), $request->validated());

        $this->auditLogService->record('quotation_created', $quotation, $request->user(), [
            'service_request_id' => $serviceRequest->getKey(),
        ], $request);

        $this->notificationService->quotationCreated($serviceRequest->client);

        return redirect()->route('professional.quotes.show', $quotation)->with('success', 'Le devis a été envoyé au client.');
    }

    public function acceptClientOffer(Request $request, Quotation $quotation): RedirectResponse
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

        if ($accepted->serviceRequest->client !== null) {
            $this->notificationService->clientCounterOfferAccepted($accepted->serviceRequest->client);
        }

        if ($accepted->order !== null) {
            return redirect()->route('professional.orders.show', $accepted->order)
                ->with('success', 'Contre-proposition acceptée. La commande a été créée et attend le paiement du client.');
        }

        return redirect()->route('professional.quotes.show', $accepted)
            ->with('warning', 'La proposition a été acceptée, mais la commande associée doit être vérifiée par le support.');
    }

    public function rejectClientOffer(Request $request, Quotation $quotation): RedirectResponse
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

        if ($rejected->serviceRequest->client !== null) {
            $this->notificationService->clientCounterOfferRejected($rejected->serviceRequest->client);
        }

        return redirect()->route('professional.quotes.show', $rejected)
            ->with('success', 'Contre-proposition refusée. La décision a été enregistrée.');
    }

    public function counterOffer(StoreQuotationOfferRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('createOffer', $quotation);

        $updated = $this->negotiationService->counterOffer(
            $quotation,
            $request->user(),
            $request->validated(),
        );
        $updated->load('serviceRequest.client');

        $this->auditLogService->record(
            'quotation_counter_offer_created',
            $updated,
            $request->user(),
            [
                'service_request_id' => $updated->service_request_id,
                'offer_id' => $updated->current_offer_id,
            ],
            $request,
        );

        if ($updated->serviceRequest->client !== null) {
            $this->notificationService->counterOfferReceived($updated->serviceRequest->client);
        }

        return redirect()->route('professional.quotes.show', $updated)
            ->with('success', 'Votre nouvelle proposition a été envoyée au client.');
    }
}
