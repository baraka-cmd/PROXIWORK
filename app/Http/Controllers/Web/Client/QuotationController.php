<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotation\IndexClientQuotationsRequest;
use App\Http\Requests\Quotation\StoreQuotationOfferRequest;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use App\Services\Quotation\QuotationExpirationService;
use App\Services\Quotation\QuotationNegotiationService;
use App\Services\Quotation\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
        private readonly QuotationNegotiationService $negotiationService,
        private readonly QuotationExpirationService $expirationService,
        private readonly AuditLogService $auditLogService,
        private readonly TransactionalNotificationService $notificationService,
    ) {}

    public function index(IndexClientQuotationsRequest $request): View
    {
        $clientId = $request->user()->getKey();

        // The scheduled command is the primary expiry mechanism. Running the
        // same idempotent service for this client also keeps their dashboard
        // accurate if the scheduler was delayed.
        $this->expirationService->expireOverdue($clientId);

        $filters = $request->validated();
        $ownedQuotes = Quotation::query()
            ->whereHas('serviceRequest', fn ($query) => $query->where('client_id', $clientId));

        $reviewQuery = fn () => (clone $ownedQuotes)
            ->whereIn('quotations.status', [
                QuotationStatus::SENT->value,
                QuotationStatus::NEGOTIATING->value,
            ])
            ->whereHas('currentOffer', fn ($query) => $query
                ->where('actor_type', QuotationOfferActor::PROFESSIONAL->value)
                ->where('valid_until', '>', now()));

        $negotiatingQuery = fn () => (clone $ownedQuotes)
            ->where('quotations.status', QuotationStatus::NEGOTIATING->value);

        $acceptedQuery = fn () => (clone $ownedQuotes)
            ->where('quotations.status', QuotationStatus::ACCEPTED->value);

        $historyQuery = fn () => (clone $ownedQuotes)
            ->whereIn('quotations.status', [
                QuotationStatus::REJECTED->value,
                QuotationStatus::EXPIRED->value,
                QuotationStatus::WITHDRAWN->value,
            ]);

        $statistics = [
            'review' => $reviewQuery()->count(),
            'negotiating' => $negotiatingQuery()->count(),
            'accepted' => $acceptedQuery()->count(),
            'history' => $historyQuery()->count(),
        ];

        $quotesQuery = (clone $ownedQuotes)->with([
            'serviceRequest.service',
            'serviceRequest.professional.user',
            'currentOffer',
            'order',
        ]);

        if (!empty($filters['bucket'])) {
            match ($filters['bucket']) {
                'review' => $quotesQuery
                    ->whereIn('quotations.status', [
                        QuotationStatus::SENT->value,
                        QuotationStatus::NEGOTIATING->value,
                    ])
                    ->whereHas('currentOffer', fn ($query) => $query
                        ->where('actor_type', QuotationOfferActor::PROFESSIONAL->value)
                        ->where('valid_until', '>', now())),
                'negotiating' => $quotesQuery->where('quotations.status', QuotationStatus::NEGOTIATING->value),
                'accepted' => $quotesQuery->where('quotations.status', QuotationStatus::ACCEPTED->value),
                'history' => $quotesQuery->whereIn('quotations.status', [
                    QuotationStatus::REJECTED->value,
                    QuotationStatus::EXPIRED->value,
                    QuotationStatus::WITHDRAWN->value,
                ]),
            };
        }

        if (!empty($filters['status'])) {
            $quotesQuery->where('quotations.status', $filters['status']);
        }

        if (!empty($filters['q'])) {
            $term = $filters['q'];
            $referenceId = preg_match('/^(?:DEV(?:IS)?[-\s]?)?0*(\d+)$/i', $term, $matches) === 1
                ? (int) $matches[1]
                : null;

            $quotesQuery->where(function ($query) use ($term, $referenceId): void {
                if ($referenceId !== null && $referenceId > 0) {
                    $query->where('quotations.id', $referenceId);
                }

                $query->orWhereHas('serviceRequest', function ($requestQuery) use ($term): void {
                    $requestQuery
                        ->where('title', 'like', '%'.$term.'%')
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('title', 'like', '%'.$term.'%'))
                        ->orWhereHas('professional.user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$term.'%'));
                });
            });
        }

        if (!empty($filters['received_from'])) {
            $quotesQuery->whereDate('quotations.created_at', '>=', $filters['received_from']);
        }

        if (!empty($filters['received_to'])) {
            $quotesQuery->whereDate('quotations.created_at', '<=', $filters['received_to']);
        }

        if (!empty($filters['validity'])) {
            match ($filters['validity']) {
                'valid' => $quotesQuery
                    ->whereIn('quotations.status', [
                        QuotationStatus::SENT->value,
                        QuotationStatus::NEGOTIATING->value,
                    ])
                    ->whereHas('currentOffer', fn ($query) => $query->where('valid_until', '>', now())),
                'expiring' => $quotesQuery
                    ->whereIn('quotations.status', [
                        QuotationStatus::SENT->value,
                        QuotationStatus::NEGOTIATING->value,
                    ])
                    ->whereHas('currentOffer', fn ($query) => $query
                        ->where('valid_until', '>', now())
                        ->where('valid_until', '<=', now()->addDays(3))),
                'expired' => $quotesQuery->where('quotations.status', QuotationStatus::EXPIRED->value),
            };
        }

        if (!empty($filters['currency'])) {
            $quotesQuery->whereHas('currentOffer', fn ($query) => $query->where('currency', $filters['currency']));
        }

        $sort = $filters['sort'] ?? 'newest';

        if (in_array($sort, ['deadline', 'amount_asc', 'amount_desc'], true)) {
            $quotesQuery
                ->leftJoin('quotation_offers as quote_current_offer', 'quote_current_offer.id', '=', 'quotations.current_offer_id')
                ->select('quotations.*');

            if ($sort === 'deadline') {
                $quotesQuery->orderByRaw('quote_current_offer.valid_until IS NULL')->orderBy('quote_current_offer.valid_until');
            } else {
                // Amounts are grouped by currency first: USD and CDF are never
                // presented as directly comparable numeric values.
                $quotesQuery->orderBy('quote_current_offer.currency')
                    ->orderBy('quote_current_offer.amount', $sort === 'amount_asc' ? 'asc' : 'desc');
            }

            $quotesQuery->orderByDesc('quotations.id');
        } elseif ($sort === 'oldest') {
            $quotesQuery->orderBy('quotations.created_at')->orderBy('quotations.id');
        } else {
            $quotesQuery->orderByDesc('quotations.created_at')->orderByDesc('quotations.id');
        }

        $quotes = $quotesQuery->paginate(12)->withQueryString();

        $currencies = QuotationOffer::query()
            ->whereIn('id', (clone $ownedQuotes)->whereNotNull('current_offer_id')->select('current_offer_id'))
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency');

        return view('client.quotes.index', compact('quotes', 'filters', 'statistics', 'currencies'));
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        $this->expirationService->expireOverdue($request->user()->getKey());

        $quotation->refresh()->load([
            'serviceRequest.service',
            'serviceRequest.professional.user',
            'serviceRequest.address',
            'currentOffer.creator',
            'acceptedOffer.creator',
            'offers.creator',
            'events.actor',
            'order',
        ]);

        return view('client.quotes.show', compact('quotation'));
    }

    public function accept(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('accept', $quotation);
        $accepted = $this->quotationService->accept($quotation, $request->user());
        $accepted->load(['serviceRequest.professional.user', 'order']);

        $this->auditLogService->record(
            'quotation_accepted',
            $accepted,
            $request->user(),
            [
                'service_request_id' => $accepted->service_request_id,
                'accepted_offer_id' => $accepted->accepted_offer_id,
                'order_id' => $accepted->order?->getKey(),
            ],
            $request,
        );

        if ($accepted->serviceRequest->professional?->user !== null) {
            $this->notificationService->quotationAccepted($accepted->serviceRequest->professional->user, route('professional.quotes.show', $accepted, true));
        }

        if ($accepted->order !== null) {
            return redirect()
                ->route('client.orders.show', $accepted->order)
                ->with('success', 'Devis accepté. La commande a été créée et attend le paiement.');
        }

        return redirect()
            ->route('client.quotes.show', $accepted)
            ->with('warning', 'Le devis a été accepté, mais la commande doit être vérifiée par le support.');
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

        if ($rejected->serviceRequest->professional?->user !== null) {
            $this->notificationService->quotationRejected($rejected->serviceRequest->professional->user, route('professional.quotes.show', $rejected, true));
        }

        return redirect()->route('client.quotes.show', $rejected)->with('success', 'Devis refusé. Cette décision est enregistrée.');
    }

    public function counterOffer(
        StoreQuotationOfferRequest $request,
        Quotation $quotation,
    ): RedirectResponse
    {
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
            [
                'service_request_id' => $counterOffer->service_request_id,
                'offer_id' => $counterOffer->current_offer_id,
            ],
            $request,
        );

        if ($counterOffer->serviceRequest->professional?->user !== null) {
            $this->notificationService->counterOfferReceived($counterOffer->serviceRequest->professional->user, route('professional.quotes.show', $counterOffer, true));
        }

        return redirect()->route('client.quotes.show', $counterOffer)->with('success', 'Votre contre-proposition a été envoyée. Le devis attend maintenant la réponse du professionnel.');
    }
}
