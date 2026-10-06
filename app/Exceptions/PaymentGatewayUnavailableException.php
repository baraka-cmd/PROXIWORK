<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PaymentGatewayUnavailableException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Le fournisseur de paiement est temporairement indisponible.', 0, $previous);
    }
}
