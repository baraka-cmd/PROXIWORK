<?php

declare(strict_types=1);

namespace AppEnums;

enum ProfessionalVerificationStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
}
