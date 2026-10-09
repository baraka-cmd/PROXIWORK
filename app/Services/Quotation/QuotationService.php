<?php

declare(strict_types=1);

namespace App\Services\Quotation;

use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Quotation;
use App\Models\QuotationEvent;
use App\Models\QuotationOffer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use App\Services\ServiceRequest\ServiceRequestLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly ServiceRequestLifecycleService $requestLifecycle,
        private readonly OrderService $orderService,
    ) {}

    public function create(ServiceRequest $serviceRequest, User $professional, array $data): Quotation
    {
        return DB::transaction(function () use ($serviceRequest, $professional, $data): Quotation {
            $request = ServiceRequest::query()
                ->lockForUpdate()
                ->with('professional')
                ->findOrFail($serviceRequest->getKey());

            if ($request->status !== ServiceRequestStatus::REQUESTED) {
                throw ValidationException::withMessages([
                    'status' => 'Un devis ne peut être créé que pour une demande en attente de proposition.',
                ]);
            }

            if ($request->professional?->user_id !== $professional->getKey()) {
                throw ValidationException::withMessages([
                    'service_request' => 'Vous ne pouvez pas créer un devis pour cette demande.',
                ]);
            }

            if (Quotation::query()->where('service_request_id', $request->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'service_request' => 'Cette demande possède déjà un devis.',
                ]);
            }

            $quotation = Quotation::query()->forceCreate([
                'service_request_id' => $request->getKey(),
                'status' => QuotationStatus::SENT,
            ]);

            $offer = $this->createOffer($quotation, $professional, QuotationOfferActor::PROFESSIONAL, $data);

            $quotation->forceFill(['current_offer_id' => $offer->getKey()])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $quotation->getKey(),
                'actor_id' => $professional->getKey(),
                'type' => 'quote_created',
                'offer_id' => $offer->getKey(),
                'created_at' => now(),
            ]);

            $this->requestLifecycle->markQuoted($request, $professional);

            return $quotation->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events']);
        });
    }

    public function accept(Quotation $quotation, User $client): Quotation
    {
        return DB::transaction(function () use ($quotation, $client): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with('serviceRequest')
                ->findOrFail($quotation->getKey());

            if ($locked->serviceRequest->client_id !== $client->getKey()) {
                throw ValidationException::withMessages(['quotation' => 'Vous ne pouvez pas accepter ce devis.']);
            }

            if (! in_array($locked->status, [QuotationStatus::SENT, QuotationStatus::NEGOTIATING], true)) {
                throw ValidationException::withMessages(['status' => 'Ce devis ne peut plus être accepté.']);
            }

            $offer = QuotationOffer::query()->findOrFail($locked->current_offer_id);

            if ($offer->valid_until->isPast()) {
                throw ValidationException::withMessages(['valid_until' => 'Cette offre a expiré.']);
            }

            $locked->forceFill([
                'status' => QuotationStatus::ACCEPTED,
                'accepted_offer_id' => $offer->getKey(),
                'accepted_at' => now(),
            ])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'actor_id' => $client->getKey(),
                'type' => 'offer_accepted',
                'offer_id' => $offer->getKey(),
                'created_at' => now(),
            ]);

            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($locked->service_request_id);
            if ($request->status !== ServiceRequestStatus::QUOTED) {
                throw ValidationException::withMessages(['status' => 'La demande n’est plus dans un état compatible avec l’acceptation.']);
            }

            $this->requestLifecycle->accept($request, $client);
            $this->orderService->createFromAcceptedQuotation($locked, $client);

            return $locked->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events']);
        });
    }

    public function reject(Quotation $quotation, User $client): Quotation
    {
        return DB::transaction(function () use ($quotation, $client): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with('serviceRequest')
                ->findOrFail($quotation->getKey());

            if ($locked->serviceRequest->client_id !== $client->getKey()) {
                throw ValidationException::withMessages(['quotation' => 'Vous ne pouvez pas refuser ce devis.']);
            }

            if (! in_array($locked->status, [QuotationStatus::SENT, QuotationStatus::NEGOTIATING], true)) {
                throw ValidationException::withMessages(['status' => 'Ce devis ne peut plus être refusé.']);
            }

            $locked->forceFill([
                'status' => QuotationStatus::REJECTED,
                'rejected_at' => now(),
            ])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'actor_id' => $client->getKey(),
                'type' => 'quote_rejected',
                'offer_id' => $locked->current_offer_id,
                'created_at' => now(),
            ]);

            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($locked->service_request_id);
            if ($request->status === ServiceRequestStatus::QUOTED) {
                $request->forceFill(['status' => ServiceRequestStatus::REJECTED])->save();
                $request->statusHistories()->create([
                    'from_status' => ServiceRequestStatus::QUOTED->value,
                    'to_status' => ServiceRequestStatus::REJECTED->value,
                    'changed_by' => $client->getKey(),
                    'reason' => 'Quotation rejected by client',
                    'created_at' => now(),
                ]);
            }

            return $locked->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events']);
        });
    }

    public function createOffer(
        Quotation $quotation,
        User $actor,
        QuotationOfferActor $actorType,
        array $data,
    ): QuotationOffer {
        $version = ((int) $quotation->offers()->max('version')) + 1;

        return QuotationOffer::query()->forceCreate([
            'quotation_id' => $quotation->getKey(),
            'created_by' => $actor->getKey(),
            'actor_type' => $actorType,
            'version' => $version,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency']),
            'description' => $data['description'],
            'duration_value' => $data['duration_value'],
            'duration_unit' => $data['duration_unit'],
            'conditions' => $data['conditions'] ?? null,
            'valid_until' => $data['valid_until'],
            'created_at' => now(),
        ]);
    }
}
