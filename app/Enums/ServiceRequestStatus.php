<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case DRAFT = 'draft';
    case REQUESTED = 'requested';
    case CANCELLED = 'cancelled';
    case QUOTED = 'quoted';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::REQUESTED => 'En attente du professionnel',
            self::CANCELLED => 'Annulée',
            self::QUOTED => 'Devis à consulter',
            self::ACCEPTED => 'Acceptée — suite en commande',
            self::REJECTED => 'Refusée',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::ACCEPTED, self::CANCELLED, self::REJECTED], true);
    }

    public function requiresClientAction(): bool
    {
        return $this === self::QUOTED;
    }

    public function awaitsProfessional(): bool
    {
        return $this === self::REQUESTED;
    }
}
