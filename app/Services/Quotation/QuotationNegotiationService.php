<?php

declare(strict_types=1);

namespace App\Services\Quotation;

use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\QuotationEvent;
use App\Models\QuotationOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationNegotiationService
{
    public function counterOffer(Quotation $quotation, User $actor, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $actor, $data): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with('serviceRequest.professional')
                ->findOrFail($quotation->getKey());

            if (! in_array($locked->status, [QuotationStatus::SENT, QuotationStatus::NEGOTIATING], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Ce devis ne peut plus faire l’objet d’une négociation.',
                ]);
            }

            $request = $locked->serviceRequest;
            $isClient = $request->client_id === $actor->getKey();
            $isProfessional = $request->professional?->user_id === $actor->getKey();

            if (! $isClient && ! $isProfessional) {
                throw ValidationException::withMessages([
                    'quotation' => 'Vous ne pouvez pas négocier ce devis.',
                ]);
            }

            $current = QuotationOffer::query()->findOrFail($locked->current_offer_id);

            if (! $current->valid_until->isFuture()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'L’offre courante a expiré. Une négociation n’est plus possible.',
                ]);
            }

            $actorType = $isClient
                ? QuotationOfferActor::CLIENT
                : QuotationOfferActor::PROFESSIONAL;

            if ($current->actor_type === $actorType) {
                throw ValidationException::withMessages([
                    'status' => 'Une réponse de l’autre partie est nécessaire avant une nouvelle proposition.',
                ]);
            }

            if (strtoupper($data['currency']) !== strtoupper($current->currency)) {
                throw ValidationException::withMessages([
                    'currency' => 'La devise doit rester identique pendant une négociation afin de comparer correctement les montants.',
                ]);
            }

            $offer = QuotationOffer::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'created_by' => $actor->getKey(),
                'actor_type' => $actorType,
                'version' => ((int) $locked->offers()->max('version')) + 1,
                'amount' => $data['amount'],
                'currency' => strtoupper($current->currency),
                'description' => $data['description'],
                'duration_value' => $data['duration_value'],
                'duration_unit' => $data['duration_unit'],
                'conditions' => $data['conditions'] ?? null,
                'valid_until' => $data['valid_until'],
                'created_at' => now(),
            ]);

            $locked->forceFill([
                'current_offer_id' => $offer->getKey(),
                'status' => QuotationStatus::NEGOTIATING,
            ])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'actor_id' => $actor->getKey(),
                'type' => 'counter_offer_created',
                'offer_id' => $offer->getKey(),
                'metadata' => ['version' => $offer->version],
                'created_at' => now(),
            ]);

            return $locked->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events']);
        });
    }

    public function renewExpiredQuotation(Quotation $quotation, User $professional, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $professional, $data): Quotation {
            $locked = Quotation::query()
                ->lockForUpdate()
                ->with('serviceRequest.professional')
                ->findOrFail($quotation->getKey());

            if ($locked->serviceRequest->professional?->user_id !== $professional->getKey()) {
                throw ValidationException::withMessages([
                    'quotation' => 'Vous ne pouvez pas renouveler ce devis.',
                ]);
            }

            if ($locked->status !== QuotationStatus::EXPIRED) {
                throw ValidationException::withMessages([
                    'status' => 'Seul un devis expiré peut être renouvelé par cette procédure.',
                ]);
            }

            if ($locked->accepted_offer_id !== null || $locked->order()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Un devis déjà accepté ou associé à une commande ne peut pas être renouvelé.',
                ]);
            }

            $current = QuotationOffer::query()
                ->lockForUpdate()
                ->findOrFail($locked->current_offer_id);

            if ($current->valid_until->isFuture()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'La proposition courante n’est pas encore expirée.',
                ]);
            }

            if (strtoupper($data['currency']) !== strtoupper($current->currency)) {
                throw ValidationException::withMessages([
                    'currency' => 'Le renouvellement conserve la devise du devis initial.',
                ]);
            }

            $offer = QuotationOffer::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'created_by' => $professional->getKey(),
                'actor_type' => QuotationOfferActor::PROFESSIONAL,
                'version' => ((int) $locked->offers()->max('version')) + 1,
                'amount' => $data['amount'],
                'currency' => strtoupper($current->currency),
                'description' => $data['description'],
                'duration_value' => $data['duration_value'],
                'duration_unit' => $data['duration_unit'],
                'conditions' => $data['conditions'] ?? null,
                'valid_until' => $data['valid_until'],
                'created_at' => now(),
            ]);

            $locked->forceFill([
                'current_offer_id' => $offer->getKey(),
                'status' => QuotationStatus::SENT,
                'rejected_at' => null,
                'withdrawn_at' => null,
            ])->save();

            QuotationEvent::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'actor_id' => $professional->getKey(),
                'type' => 'quote_renewed',
                'offer_id' => $offer->getKey(),
                'metadata' => [
                    'previous_offer_id' => $current->getKey(),
                    'previous_version' => $current->version,
                    'new_version' => $offer->version,
                ],
                'created_at' => now(),
            ]);

            return $locked->refresh()->load(['currentOffer', 'acceptedOffer', 'offers', 'events']);
        }, attempts: 3);
    }
}
