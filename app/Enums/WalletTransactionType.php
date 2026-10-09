<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletTransactionType: string
{
    case EARNING = 'earning';
    case COMMISSION = 'commission';
    case WITHDRAWAL = 'withdrawal';
    case REFUND = 'refund';
    case ADJUSTMENT = 'adjustment';
    case REVERSAL = 'reversal';
}
