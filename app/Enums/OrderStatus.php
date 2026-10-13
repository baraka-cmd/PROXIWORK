<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case CONFIRMED = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case DELIVERY_SUBMITTED = 'delivery_submitted';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';

    public function isOperationallyActive(): bool
    {
        return match ($this) {
            self::CONFIRMED, self::IN_PROGRESS, self::DELIVERY_SUBMITTED => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function operationallyActiveValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            array_filter(self::cases(), static fn (self $status): bool => $status->isOperationallyActive()),
        );
    }
}
