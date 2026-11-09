<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Admin\User\AdminUserService;
use Illuminate\Console\Command;

class ExpireUserSuspensions extends Command
{
    protected $signature = 'users:expire-suspensions';

    protected $description = 'Réactive les comptes dont la période de suspension est arrivée à son terme.';

    public function handle(AdminUserService $service): int
    {
        $count = $service->expireSuspensions();

        $this->info(sprintf('%d compte(s) réactivé(s) après expiration de suspension.', $count));

        return self::SUCCESS;
    }
}
