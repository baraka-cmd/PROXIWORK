<?php

declare(strict_types=1);

namespace App\Enums;

enum ServicePricingType: string
{
    case FIXED = 'fixed';
    case FROM = 'from';
    case RANGE = 'range';
    case QUOTE = 'quote';
}
