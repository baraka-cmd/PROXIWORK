<?php

declare(strict_types=1);

namespace App\Contracts\Payments;

use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;

interface PaymentGateway
{
    public function initiate(PaymentRequest $request): PaymentResult;
}
