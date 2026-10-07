<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportReasonCode: string
{
    case SPAM = 'SPAM';
    case FRAUD = 'FRAUD';
    case HARASSMENT = 'HARASSMENT';
    case ABUSIVE_CONTENT = 'ABUSIVE_CONTENT';
    case INAPPROPRIATE_CONTENT = 'INAPPROPRIATE_CONTENT';
    case FAKE_PROFILE = 'FAKE_PROFILE';
    case FAKE_SERVICE = 'FAKE_SERVICE';
    case SCAM = 'SCAM';
    case COPYRIGHT = 'COPYRIGHT';
    case OTHER = 'OTHER';
}
