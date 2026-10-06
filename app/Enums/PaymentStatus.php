<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';
}
