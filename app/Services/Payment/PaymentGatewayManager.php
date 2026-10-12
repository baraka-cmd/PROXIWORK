<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentProvider;
use App\Exceptions\PaymentGatewayUnavailableException;
use RuntimeException;

class PaymentGatewayManager
{
    public function __construct(
        private readonly PaymentGateway $fakeGateway,
    ) {}

    public function gateway(PaymentProvider $provider): PaymentGateway
    {
        if ($provider === PaymentProvider::FAKE
            && (app()->environment('production') || config('payment.simulation_enabled') !== true)) {
            throw new PaymentGatewayUnavailableException(
                new RuntimeException('Le mode de simulation est désactivé dans cet environnement.')
            );
        }

        return match ($provider) {
            PaymentProvider::FAKE => $this->fakeGateway,
            default => throw new RuntimeException(
                sprintf('Le fournisseur de paiement [%s] n’est pas encore configuré.', $provider->value)
            ),
        };
    }
}
