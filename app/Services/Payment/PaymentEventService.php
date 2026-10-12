<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\PaymentEvent;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

class PaymentEventService
{
    public function record(
        PaymentTransaction $transaction,
        string $eventType,
        ?string $fromStatus,
        string $toStatus,
        string $source,
        array $metadata = [],
        ?string $eventKey = null,
    ): PaymentEvent {
        $eventKey ??= 'evt-'.Str::uuid();

        $existing = PaymentEvent::query()->where('event_key', $eventKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return PaymentEvent::query()->forceCreate([
            'payment_id' => $transaction->payment_id,
            'payment_transaction_id' => $transaction->getKey(),
            'event_key' => $eventKey,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'source' => $source,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
