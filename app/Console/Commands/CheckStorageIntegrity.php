<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Admin\StorageIntegrityService;
use Illuminate\Console\Command;

class CheckStorageIntegrity extends Command
{
    protected $signature = 'storage:integrity-check {--max-records=0 : Optional upper limit for a controlled diagnostic run}';

    protected $description = 'Check that known PROXIWORK file references still exist on their configured disks.';

    public function handle(StorageIntegrityService $integrity): int
    {
        $maxRecords = max((int) $this->option('max-records'), 0);
        $result = $integrity->run($maxRecords);

        $this->info(sprintf(
            'Contrôle #%d terminé : %d référence(s), %d fichier(s) manquant(s), %d erreur(s) de vérification.',
            $result['id'],
            $result['records_checked'],
            $result['missing_files'],
            $result['check_errors'],
        ));

        foreach ($result['table_results'] as $table => $counts) {
            $this->line(sprintf(
                '%s : %d vérifié(s), %d manquant(s), %d erreur(s).',
                $table,
                $counts['checked'],
                $counts['missing'],
                $counts['errors'],
            ));
        }

        return self::SUCCESS;
    }
}
