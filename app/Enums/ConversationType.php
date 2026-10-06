<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationType: string
{
    case CLIENT_PROFESSIONAL = 'client_professional';
    case SUPPORT = 'support';
}
