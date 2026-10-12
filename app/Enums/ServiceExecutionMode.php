<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceExecutionMode: string
{
    case CLIENT_LOCATION = 'client_location';
    case REMOTE = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT_LOCATION => 'Chez le client / lieu d’intervention',
            self::REMOTE => 'À distance',
        };
    }

    public function requiresClientAddress(): bool
    {
        return $this === self::CLIENT_LOCATION;
    }
}
