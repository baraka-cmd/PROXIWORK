<?php

declare(strict_types=1);

namespace App\Enums;

enum SupportTicketCategory: string
{
    case ACCOUNT = 'ACCOUNT';
    case PAYMENT = 'PAYMENT';
    case ORDER = 'ORDER';
    case PROFESSIONAL = 'PROFESSIONAL';
    case SERVICE = 'SERVICE';
    case VERIFICATION = 'VERIFICATION';
    case TECHNICAL = 'TECHNICAL';
    case OTHER = 'OTHER';
}
