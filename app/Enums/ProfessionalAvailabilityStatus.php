<?php

declare(strict_types=1);

namespace App\Enums;

enum ProfessionalAvailabilityStatus: string
{
    case UNKNOWN = 'unknown';
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
}
