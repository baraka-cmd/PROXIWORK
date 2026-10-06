<?php

declare(strict_types=1);

namespace App\Enums;

enum WithdrawalStatus: string
{
    case REQUESTED = 'requested';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED, self::CANCELLED => true,
            default => false,
        };
    }
}
