<?php

declare(strict_types=1);

namespace App\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;

class FakePaymentGateway implements PaymentGateway
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        $status = PaymentStatus::from(
            (string) config('payment.fake.status', PaymentStatus::PENDING->value)
        );

        return new PaymentResult(
            status: $status,
            amount: $request->amount,
            currency: $request->currency,
            providerReference: 'FAKE-'.$request->idempotencyKey,
            instructions: $status === PaymentStatus::PENDING
                ? 'Paiement simulé en attente de confirmation.'
                : null,
            metadata: [
                'gateway' => 'fake',
                'idempotent' => true,
            ],
            failureCode: $status === PaymentStatus::FAILED ? 'FAKE_PAYMENT_FAILED' : null,
            failureMessage: $status === PaymentStatus::FAILED ? 'Échec simulé du paiement.' : null,
        );
    }
}
