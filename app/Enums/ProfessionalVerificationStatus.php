<?php

declare(strict_types=1);

namespace App\Enums;

enum ProfessionalVerificationStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case ADDITIONAL_DOCUMENTS_REQUESTED = 'additional_documents_requested';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
}
