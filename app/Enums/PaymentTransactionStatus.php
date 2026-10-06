<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';

    public function isFinal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED, self::CANCELLED, self::EXPIRED, self::REFUNDED => true,
            default => false,
        };
    }
}
