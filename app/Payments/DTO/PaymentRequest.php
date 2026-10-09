<?php

declare(strict_types=1);

namespace App\Payments\DTO;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;

final readonly class PaymentRequest
{
    public function __construct(
        public int $orderId,
        public string $idempotencyKey,
        public string $amount,
        public string $currency,
        public PaymentMethod $method,
        public PaymentProvider $provider,
        public array $metadata = [],
    ) {}
}
