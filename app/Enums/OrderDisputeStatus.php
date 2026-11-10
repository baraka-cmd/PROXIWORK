<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderDisputeStatus: string
{
    case OPEN = 'open';
    case UNDER_REVIEW = 'under_review';
    case AWAITING_CLIENT = 'awaiting_client';
    case AWAITING_PROFESSIONAL = 'awaiting_professional';
    case MEDIATION = 'mediation';
    case DECISION_PENDING = 'decision_pending';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case REOPENED = 'reopened';

    public function isActive(): bool
    {
        return ! in_array($this, [self::RESOLVED, self::CLOSED], true);
    }
}
