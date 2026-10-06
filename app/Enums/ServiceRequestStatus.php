<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case DRAFT = 'draft';
    case REQUESTED = 'requested';
    case CANCELLED = 'cancelled';
    case QUOTED = 'quoted';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
