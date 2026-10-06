<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewStatus: string
{
    case PUBLISHED = 'published';
    case HIDDEN = 'hidden';
}
