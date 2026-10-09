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
}
