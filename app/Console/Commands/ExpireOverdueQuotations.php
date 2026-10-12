<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Quotation\QuotationExpirationService;
use Illuminate\Console\Command;

class ExpireOverdueQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Expire quotations whose active offer validity has elapsed';

    public function handle(QuotationExpirationService $expirationService): int
    {
        $count = $expirationService->expireOverdue();

        $this->info(sprintf('%d devis expiré(s).', $count));

        return self::SUCCESS;
    }
}
