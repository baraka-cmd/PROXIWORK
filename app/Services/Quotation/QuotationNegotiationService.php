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

            if ($current->valid_until->isPast()) {
                throw ValidationException::withMessages([
                    'valid_until' => 'L’offre courante a expiré. Une négociation n’est plus possible.',
                ]);
            }

            $actorType = $isClient
                ? QuotationOfferActor::CLIENT
                : QuotationOfferActor::PROFESSIONAL;

            $offer = QuotationOffer::query()->forceCreate([
                'quotation_id' => $locked->getKey(),
                'created_by' => $actor->getKey(),
                'actor_type' => $actorType,
                'version' => ((int) $locked->offers()->max('version')) + 1,
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency']),
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
}
