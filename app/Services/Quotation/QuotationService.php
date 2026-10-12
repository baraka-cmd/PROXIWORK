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
use App\Services\Notification\TransactionalNotificationService;
use App\Services\ServiceRequest\ServiceRequestLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly ServiceRequestLifecycleService $requestLifecycle,
        private readonly OrderService $orderService,
        private readonly TransactionalNotificationService $notificationService,
    ) {}

    public function create(ServiceRequest $serviceRequest, User $professional, array $data): Quotation
    {
        return DB::transaction(function () use ($serviceRequest, $professional, $data): Quotation {
            $request = ServiceRequest::query()
                ->lockForUpdate()
                ->with(['professional', 'client'])
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

            $quotation = $quotation->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events']);

            if ($request->client !== null) {
                $this->notificationService->quotationCreated(
                    $request->client,
                    route('client.quotes.show', $quotation, true),
                    'quotation-created:'.$quotation->getKey(),
                );
            }

            return $quotation;
        });
    }

    public function accept(Quotation $quotation, User $client): Quotation
    {
        return DB::transaction(function () use ($quotation, $client): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with(['serviceRequest', 'serviceRequest.professional.user'])
                ->findOrFail($quotation->getKey());

            if ($locked->serviceRequest->client_id !== $client->getKey()) {
                throw ValidationException::withMessages(['quotation' => 'Vous ne pouvez pas accepter ce devis.']);
            }

            if (!in_array($locked->status, [QuotationStatus::SENT, QuotationStatus::NEGOTIATING], true)) {
                throw ValidationException::withMessages(['status' => 'Ce devis ne peut plus être accepté.']);
            }

            $offer = QuotationOffer::query()->findOrFail($locked->current_offer_id);

            if ($offer->actor_type !== QuotationOfferActor::PROFESSIONAL) {
                throw ValidationException::withMessages([
                    'status' => 'Votre contre-proposition attend la réponse du professionnel et ne peut pas être acceptée par vous-même.',
                ]);
            }

            if (!$offer->valid_until->isFuture()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'Cette offre a expiré. Le professionnel doit la renouveler avant toute acceptation.',
                ]);
            }

            $request = ServiceRequest::query()->with('professional.user')->lockForUpdate()->findOrFail($locked->service_request_id);

            if ($request->status !== ServiceRequestStatus::QUOTED) {
                throw ValidationException::withMessages([
                    'status' => 'La demande n’est plus dans un état compatible avec l’acceptation.',
                ]);
            }

            if ($request->address_id === null) {
                throw ValidationException::withMessages([
                    'address' => 'Une adresse d’exécution appartenant au client est requise avant l’acceptation.',
                ]);
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
                'metadata' => ['version' => $offer->version],
                'created_at' => now(),
            ]);

            $this->requestLifecycle->accept($request, $client);
            $this->orderService->createFromAcceptedQuotation($locked, $client);

            if ($request->professional?->user !== null) {
                $this->notificationService->quotationAccepted(
                    $request->professional->user,
                    route('professional.quotes.show', $locked, true),
                    'quotation-accepted:'.$locked->getKey(),
                );
            }

            return $locked->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events', 'order']);
        });
    }

    public function acceptClientOffer(Quotation $quotation, User $professional): Quotation
    {
        return DB::transaction(function () use ($quotation, $professional): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with(['serviceRequest.professional', 'serviceRequest.client'])
                ->findOrFail($quotation->getKey());

            if ($locked->serviceRequest->professional?->user_id !== $professional->getKey()) {
                throw ValidationException::withMessages(['quotation' => 'Vous ne pouvez pas décider pour ce devis.']);
            }

            if (! in_array($locked->status, [QuotationStatus::SENT, QuotationStatus::NEGOTIATING], true)) {
                throw ValidationException::withMessages(['status' => 'Ce devis ne peut plus être accepté.']);
            }

            $offer = QuotationOffer::query()->findOrFail($locked->current_offer_id);

            if ($offer->actor_type !== QuotationOfferActor::CLIENT) {
                throw ValidationException::withMessages([
                    'status' => 'La proposition active n’est pas une contre-proposition du client.',
                ]);
            }

            if (! $offer->valid_until->isFuture()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'La contre-proposition du client a expiré.',
                ]);
            }

            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($locked->service_request_id);

            if ($request->status !== ServiceRequestStatus::QUOTED) {
                throw ValidationException::withMessages([
                    'status' => 'La demande n’est plus dans un état compatible avec l’acceptation.',
                ]);
            }

            if ($request->address_id === null) {
                throw ValidationException::withMessages([
                    'address' => 'Le client doit définir une adresse d’exécution avant la création de la commande.',
                ]);
            }

            $locked->forceFill([
                'status' => QuotationStatus::ACCEPTED,
                'accepted_offer_id' => $offer->getKey(),
                'accepted_at' => now(),
            ])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'actor_id' => $professional->getKey(),
                'type' => 'offer_accepted_by_professional',
                'offer_id' => $offer->getKey(),
                'metadata' => ['version' => $offer->version],
                'created_at' => now(),
            ]);

            $this->requestLifecycle->accept($request, $professional);
            $this->orderService->createFromAcceptedQuotation($locked, $professional);

            if ($locked->serviceRequest->client !== null) {
                $this->notificationService->clientCounterOfferAccepted(
                    $locked->serviceRequest->client,
                    route('client.quotes.show', $locked, true),
                    'client-counter-offer-accepted:'.$locked->getKey(),
                );
            }

            return $locked->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events', 'order']);
        });
    }

    public function rejectClientOffer(Quotation $quotation, User $professional): Quotation
    {
        return DB::transaction(function () use ($quotation, $professional): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with(['serviceRequest.professional', 'serviceRequest.client'])
                ->findOrFail($quotation->getKey());

            if ($locked->serviceRequest->professional?->user_id !== $professional->getKey()) {
                throw ValidationException::withMessages(['quotation' => 'Vous ne pouvez pas décider pour ce devis.']);
            }

            if (! in_array($locked->status, [QuotationStatus::SENT, QuotationStatus::NEGOTIATING], true)) {
                throw ValidationException::withMessages(['status' => 'Ce devis ne peut plus être refusé.']);
            }

            $offer = QuotationOffer::query()->findOrFail($locked->current_offer_id);

            if ($offer->actor_type !== QuotationOfferActor::CLIENT) {
                throw ValidationException::withMessages([
                    'status' => 'La proposition active n’est pas une contre-proposition du client.',
                ]);
            }

            if (! $offer->valid_until->isFuture()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'La contre-proposition du client a expiré.',
                ]);
            }

            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($locked->service_request_id);

            if ($request->status !== ServiceRequestStatus::QUOTED) {
                throw ValidationException::withMessages([
                    'status' => 'La demande n’est plus dans un état compatible avec le refus.',
                ]);
            }

            $locked->forceFill([
                'status' => QuotationStatus::REJECTED,
                'rejected_at' => now(),
            ])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'actor_id' => $professional->getKey(),
                'type' => 'counter_offer_rejected_by_professional',
                'offer_id' => $offer->getKey(),
                'metadata' => ['version' => $offer->version],
                'created_at' => now(),
            ]);

            $this->requestLifecycle->rejectByProfessionalDuringNegotiation($request, $professional);

            if ($locked->serviceRequest->client !== null) {
                $this->notificationService->clientCounterOfferRejected(
                    $locked->serviceRequest->client,
                    route('client.quotes.show', $locked, true),
                    'client-counter-offer-rejected:'.$locked->getKey(),
                );
            }

            return $locked->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events']);
        });
    }

    public function reject(Quotation $quotation, User $client): Quotation
    {
        return DB::transaction(function () use ($quotation, $client): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with(['serviceRequest', 'serviceRequest.professional.user'])
                ->findOrFail($quotation->getKey());

            if ($locked->serviceRequest->client_id !== $client->getKey()) {
                throw ValidationException::withMessages(['quotation' => 'Vous ne pouvez pas refuser ce devis.']);
            }

            if (! in_array($locked->status, [QuotationStatus::SENT, QuotationStatus::NEGOTIATING], true)) {
                throw ValidationException::withMessages(['status' => 'Ce devis ne peut plus être refusé.']);
            }

            $offer = QuotationOffer::query()->findOrFail($locked->current_offer_id);

            if ($offer->actor_type !== QuotationOfferActor::PROFESSIONAL) {
                throw ValidationException::withMessages([
                    'status' => 'Votre contre-proposition attend la réponse du professionnel.',
                ]);
            }

            if (! $offer->valid_until->isFuture()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'Cette offre a expiré. Elle ne peut plus faire l’objet d’une décision.',
                ]);
            }

            $locked->forceFill([
                'status' => QuotationStatus::REJECTED,
                'rejected_at' => now(),
            ])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'actor_id' => $client->getKey(),
                'type' => 'quote_rejected',
                'offer_id' => $offer->getKey(),
                'metadata' => ['version' => $offer->version],
                'created_at' => now(),
            ]);

            $this->requestLifecycle->rejectByClient($locked->serviceRequest, $client);

            if ($locked->serviceRequest->professional?->user !== null) {
                $this->notificationService->quotationRejected(
                    $locked->serviceRequest->professional->user,
                    route('professional.quotes.show', $locked, true),
                    'quotation-rejected:'.$locked->getKey(),
                );
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
