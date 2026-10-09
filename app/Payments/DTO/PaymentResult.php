<?php

declare(strict_types=1);

namespace App\Payments\DTO;

use App\Enums\PaymentStatus;

final readonly class PaymentResult
{
    public function __construct(
        public PaymentStatus $status,
        public string $amount,
        public string $currency,
        public ?string $providerReference = null,
        public ?string $redirectUrl = null,
        public ?string $instructions = null,
        public array $metadata = [],
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}
}
