<?php

declare(strict_types=1);

namespace AppEnums;

enum ProfessionalAvailabilityStatus: string
{
    case UNKNOWN = 'unknown';
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
}
