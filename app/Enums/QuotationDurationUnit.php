<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotationDurationUnit: string
{
    case HOURS = 'hours';
    case DAYS = 'days';
    case WEEKS = 'weeks';
}
