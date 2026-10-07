<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';
}
