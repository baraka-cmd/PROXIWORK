<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;

interface PaymentGateway
{
    /**
     * Providers must treat PaymentRequest::idempotencyKey as an idempotency key.
     *
     * A retry after an unknown network outcome must not create a second charge.
     */
    public function initiate(PaymentRequest $request): PaymentResult;
}
