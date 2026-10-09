<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotationOfferActor: string
{
    case CLIENT = 'client';
    case PROFESSIONAL = 'professional';
}
