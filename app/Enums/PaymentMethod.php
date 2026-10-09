<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case MOBILE_MONEY = 'mobile_money';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
}
