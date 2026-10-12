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

                        return [
                            'client_id' => $quotation->serviceRequest->client_id,
                            'professional_user_id' => $quotation->serviceRequest->professional?->user_id,
                        ];
                    });

                    if ($recipients === null) {
                        continue;
                    }

                    $client = User::query()->find($recipients['client_id']);
                    if ($client !== null) {
                        $this->notificationService->quotationExpired($client);
                    }

                    $professional = $recipients['professional_user_id'] !== null
                        ? User::query()->find($recipients['professional_user_id'])
                        : null;

                    if ($professional !== null) {
                        $this->notificationService->quotationExpired($professional);
                    }

                    $expiredCount++;
                }
            });

        return $expiredCount;
    }
}
