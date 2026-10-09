<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use App\Services\Quotation\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
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

        $quotation->load(['serviceRequest.service', 'serviceRequest.client', 'currentOffer', 'acceptedOffer', 'offers', 'events']);

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
}
