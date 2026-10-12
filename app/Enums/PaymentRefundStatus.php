<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentRefundStatus: string
{
    case REQUESTED = 'requested';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function isOpen(): bool
    {
        return match ($this) {
            self::REQUESTED, self::PROCESSING => true,
            default => false,
        };
    }
}
