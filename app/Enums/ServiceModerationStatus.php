<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceModerationStatus: string
{
    case CLEAR = 'clear';
    case UNDER_REVIEW = 'under_review';
    case HIDDEN = 'hidden';
    case CHANGES_REQUESTED = 'changes_requested';
    case REJECTED = 'rejected';
}
