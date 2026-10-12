<?php

declare(strict_types=1);

namespace App\Services\Quotation;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\QuotationEvent;
use App\Models\User;
use App\Services\Notification\TransactionalNotificationService;
use Illuminate\Support\Facades\DB;

class QuotationExpirationService
{
    public function __construct(
        private readonly TransactionalNotificationService $notificationService,
    ) {}

    public function expireOverdue(?int $clientId = null): int
    {
        $expiredCount = 0;

        Quotation::query()
            ->whereIn('status', [
                QuotationStatus::SENT->value,
                QuotationStatus::NEGOTIATING->value,
            ])
            ->whereHas('currentOffer', fn ($query) => $query->where('valid_until', '<=', now()))
            ->when(
                $clientId !== null,
                fn ($query) => $query->whereHas(
                    'serviceRequest',
                    fn ($requestQuery) => $requestQuery->where('client_id', $clientId),
                ),
            )
            ->select('id')
            ->chunkById(100, function ($quotations) use (&$expiredCount): void {
                foreach ($quotations as $quotationStub) {
                    $recipients = DB::transaction(function () use ($quotationStub): ?array {
                        $quotation = Quotation::query()
                            ->lockForUpdate()
                            ->find($quotationStub->getKey());

                        if ($quotation === null || ! in_array(
                            $quotation->status,
                            [QuotationStatus::SENT, QuotationStatus::NEGOTIATING],
                            true,
                        )) {
                            return null;
                        }

                        $quotation->load([
                            'currentOffer',
                            'serviceRequest.client',
                            'serviceRequest.professional.user',
                        ]);

                        $offer = $quotation->currentOffer;

                        if ($offer === null || $offer->valid_until === null || $offer->valid_until->isFuture()) {
                            return null;
                        }

                        $quotation->forceFill(['status' => QuotationStatus::EXPIRED])->save();

                        QuotationEvent::query()->forceCreate([
                            'quotation_id' => $quotation->getKey(),
                            'actor_id' => null,
                            'type' => 'quote_expired',
                            'offer_id' => $offer->getKey(),
                            'metadata' => ['version' => $offer->version],
                            'created_at' => now(),
                        ]);

                        $client = $quotation->serviceRequest->client;
                        if ($client !== null) {
                            $this->notificationService->quotationExpired(
                                $client,
                                route('client.quotes.show', $quotation, true),
                                'quotation-expired:'.$quotation->getKey().':'.$offer->getKey(),
                            );
                        }

                        $professional = $quotation->serviceRequest->professional?->user;
                        if ($professional !== null) {
                            $this->notificationService->quotationExpired(
                                $professional,
                                route('professional.quotes.show', $quotation, true),
                                'quotation-expired:'.$quotation->getKey().':'.$offer->getKey(),
                            );
                        }

                        return ['quotation_id' => $quotation->getKey()];
                    });

                    if ($recipients === null) {
                        continue;
                    }

                    // The notification and delivery intent were persisted inside
                    // the same transaction as the EXPIRED status transition.
                    $expiredCount++;
                }
            });

        return $expiredCount;
    }
}
