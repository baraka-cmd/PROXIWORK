<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignRecipient;
use App\Notifications\CommunicationCampaignNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendCommunicationCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $campaignId)
    {
    }

    public function handle(): void
    {
        $campaign = CommunicationCampaign::query()->findOrFail($this->campaignId);
        if (! in_array($campaign->status, ['queued', 'sending'], true)) {
            return;
        }

        CommunicationCampaign::query()->whereKey($campaign->id)->update(['status' => 'sending']);

        CommunicationCampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use ($campaign): void {
                foreach ($recipients as $recipient) {
                    try {
                        DB::transaction(function () use ($recipient, $campaign): void {
                            $locked = CommunicationCampaignRecipient::query()->lockForUpdate()->find($recipient->id);
                            if ($locked === null || $locked->status !== 'pending') {
                                return;
                            }

                            $user = $locked->user()->first();
                            if ($user === null || $user->account_status->value !== 'active') {
                                $locked->update(['status' => 'skipped', 'error_code' => 'RecipientUnavailable', 'processed_at' => now()]);
                                return;
                            }

                            $user->notify(new CommunicationCampaignNotification($campaign->subject, $campaign->body, $campaign->id));
                            $locked->update(['status' => 'sent', 'processed_at' => now()]);
                            $campaign->increment('sent_count');
                        });
                    } catch (Throwable $exception) {
                        CommunicationCampaignRecipient::query()->whereKey($recipient->id)->where('status', 'pending')->update([
                            'status' => 'failed',
                            'error_code' => class_basename($exception),
                            'processed_at' => now(),
                        ]);
                        $campaign->increment('failed_count');
                        report($exception);
                    }
                }
            });

        $remaining = $campaign->recipients()->where('status', 'pending')->exists();
        if (! $remaining) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}
