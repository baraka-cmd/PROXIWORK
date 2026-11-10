<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendCommunicationCampaign;
use App\Models\CommunicationCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchScheduledCommunicationCampaigns extends Command
{
    protected $signature = 'communication:dispatch-scheduled';

    protected $description = 'Dispatch scheduled communication campaigns when their publication time is reached.';

    public function handle(): int
    {
        CommunicationCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    DB::transaction(function () use ($campaign): void {
                        $locked = CommunicationCampaign::query()->lockForUpdate()->find($campaign->id);
                        if ($locked === null || $locked->status !== 'scheduled' || $locked->scheduled_at?->isFuture()) {
                            return;
                        }

                        $locked->update(['status' => 'queued']);

                    });
                }
            });

        // Recover campaigns already marked as queued if the original request could not
        // reach the queue backend. Recipient-level locks make duplicate jobs safe.
        CommunicationCampaign::query()
            ->where('status', 'queued')
            ->orderBy('id')
            ->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    SendCommunicationCampaign::dispatch($campaign->id)->afterCommit();
                }
            });

        return self::SUCCESS;
    }
}
