<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Quotation\QuotationNegotiationService;
use App\Services\Quotation\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly QuotationNegotiationService $negotiationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'action_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', Rule::enum(QuotationStatus::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $actionRequired = (bool) ($filters['action_required'] ?? false);
        $query = Quotation::query()
            ->whereHas('serviceRequest.professional', fn ($query) => $query->where('user_id', $request->user()->getKey()))
            ->with(['serviceRequest.service', 'serviceRequest.client', 'currentOffer']);

        if ($actionRequired) {
            $query->where('status', QuotationStatus::NEGOTIATING->value)
                ->whereHas('currentOffer', fn ($offerQuery) => $offerQuery
                    ->where('actor_type', QuotationOfferActor::CLIENT->value));
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['q'] ?? null)) {
            $term = $filters['q'];
            $query->whereHas('serviceRequest', function ($requestQuery) use ($term): void {
                $requestQuery->where(function ($nested) use ($term): void {
                    $nested->where('title', 'like', '%'.$term.'%')
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', '%'.$term.'%'));
                });
            });
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $quotations = $query->latest('id')->paginate(15)->withQueryString();

        return view('professional.quotes.index', compact('quotations', 'actionRequired', 'filters'));
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

        abort_if($serviceRequest->status->value !== 'requested' || $serviceRequest->quotation()->exists(), 404);

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

        return redirect()->route('professional.quotes.show', $rejected)
            ->with('success', 'Contre-proposition refusée. La décision a été enregistrée.');
    }

    public function renew(StoreQuotationOfferRequest $request, Quotation $quotation): RedirectResponse
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

        return redirect()->route('professional.quotes.show', $renewed)
            ->with('success', 'Le devis expiré a été renouvelé avec une nouvelle version. Le client doit maintenant examiner cette proposition.');
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

        return redirect()->route('professional.quotes.show', $updated)
            ->with('success', 'Votre nouvelle proposition a été envoyée au client.');
    }
}
