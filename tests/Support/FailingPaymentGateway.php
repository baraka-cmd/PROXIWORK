<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Payments\PaymentGateway;
use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use RuntimeException;

class FailingPaymentGateway implements PaymentGateway
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        throw new RuntimeException('simulated connection loss');
    }
}
