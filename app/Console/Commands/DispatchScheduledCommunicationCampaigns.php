<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Notifications\DispatchCommunicationCampaign;
use App\Models\CommunicationCampaign;
use Illuminate\Console\Command;

class DispatchScheduledCommunicationCampaigns extends Command
{
    protected $signature = 'communications:dispatch-scheduled {--limit=50}';

    protected $description = 'Queue scheduled communication campaigns whose publication time has arrived.';

    public function handle(): int
    {
        $ids = CommunicationCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->limit(min(max((int) $this->option('limit'), 1), 200))
            ->pluck('id');

        foreach ($ids as $id) {
            $claimed = CommunicationCampaign::query()
                ->whereKey($id)
                ->where('status', 'scheduled')
                ->update(['status' => 'dispatching', 'updated_at' => now()]);
            if ($claimed === 1) {
                DispatchCommunicationCampaign::dispatch((int) $id);
            }
        }

        $this->info($ids->count().' campagne(s) planifiée(s) examinée(s).');

        return self::SUCCESS;
    }
}