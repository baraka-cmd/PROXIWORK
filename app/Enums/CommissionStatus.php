<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionStatus: string
{
    case POSTED = 'posted';
    case REVERSED = 'reversed';
}
