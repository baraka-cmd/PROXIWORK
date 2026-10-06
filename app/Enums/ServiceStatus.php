<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case UNPUBLISHED = 'unpublished';
    case ARCHIVED = 'archived';
}
