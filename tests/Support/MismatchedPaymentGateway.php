<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;

class MismatchedPaymentGateway implements PaymentGateway
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::SUCCEEDED,
            amount: '1.00',
            currency: $request->currency,
            providerReference: 'FAKE-MISMATCHED-AMOUNT',
            metadata: ['gateway' => 'mismatch-test'],
        );
    }
}
