<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotationStatus: string
{
    case SENT = 'sent';
    case NEGOTIATING = 'negotiating';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case WITHDRAWN = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::SENT => 'Envoyé au client',
            self::NEGOTIATING => 'En négociation',
            self::ACCEPTED => 'Accepté',
            self::REJECTED => 'Refusé',
            self::EXPIRED => 'Expiré',
            self::WITHDRAWN => 'Retiré',
        };
    }
}
