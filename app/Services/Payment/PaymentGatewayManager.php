<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentProvider;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(
        private readonly PaymentGateway $fakeGateway,
    ) {}

    public function gateway(PaymentProvider $provider): PaymentGateway
    {
        return match ($provider) {
            PaymentProvider::FAKE => $this->fakeGateway,
            default => throw new InvalidArgumentException(
                sprintf('Le fournisseur de paiement [%s] n’est pas encore configuré.', $provider->value)
            ),
        };
    }
}
