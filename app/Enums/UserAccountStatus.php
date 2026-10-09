<?php

declare(strict_types=1);

namespace App\Enums;

enum UserAccountStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
}
