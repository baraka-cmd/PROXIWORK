<?php

declare(strict_types=1);

namespace App\Enums;

enum ProfessionalVerificationStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case NEEDS_INFORMATION = 'needs_information';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
}
