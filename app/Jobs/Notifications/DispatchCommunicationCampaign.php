<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\CommunicationCampaign;
use App\Models\NotificationDelivery;
use App\Services\Admin\Communication\CommunicationAudienceService;
use App\Services\Notification\NotificationTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DispatchCommunicationCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $campaignId) {}

    public function handle(NotificationTemplateRenderer $renderer, CommunicationAudienceService $audienceService): void
    {
        $campaign = CommunicationCampaign::query()->findOrFail($this->campaignId);
        if (in_array($campaign->status, ['dispatching', 'scheduled'], true) === false) {
            return;
        }

        $campaign->update(['status' => 'dispatching', 'started_at' => $campaign->started_at ?? now()]);
        $audience = $campaign->audience ?? [];
        $users = $audienceService->query($audience)->orderBy('id');

        $created = 0;
        $failed = 0;
        $users->chunkById(200, function ($recipients) use ($campaign, $renderer, &$created, &$failed): void {
            foreach ($recipients as $recipient) {
                try {
                    DB::transaction(function () use ($campaign, $renderer, $recipient, &$created): void {
                        $deduplicationKey = hash('sha256', 'campaign|'.$campaign->id.'|'.$recipient->getKey());
                        if ($recipient->notifications()->where('deduplication_key', $deduplicationKey)->exists()) {
                            return;
                        }

                        $rendered = $renderer->render('campaign', $recipient, $campaign->title, $campaign->body);
                        $notification = new DatabaseNotification;
                        $notification->forceFill([
                            'id' => (string) Str::uuid(),
                            'type' => 'App\\Notifications\\AccountActivityNotification',
                            'data' => [
                                'title' => $rendered['title'],
                                'message' => $rendered['body'],
                                'action' => 'announcement',
                                'url' => '/announcements',
                                'priority' => $campaign->priority,
                                'campaign_id' => $campaign->id,
                                'template_key' => 'campaign',
                                'template_version' => $rendered['version'],
                            ],
                            'read_at' => null,
                            'archived_at' => null,
                            'deduplication_key' => $deduplicationKey,
                        ]);
                        $recipient->notifications()->save($notification);

                        foreach ($campaign->channels ?? [] as $channel) {
                            if (in_array($channel, ['mail', 'sms', 'push'], true) === false) {
                                continue;
                            }
                            NotificationDelivery::query()->firstOrCreate(
                                ['notification_id' => $notification->id, 'channel' => $channel],
                                ['user_id' => $recipient->getKey(), 'status' => 'PENDING', 'attempts' => 0, 'available_at' => now()],
                            );
                        }
                        $created = $created + 1;
                    });
                } catch (Throwable $exception) {
                    $failed = $failed + 1;
                    Log::warning('Communication campaign recipient could not be processed.', [
                        'campaign_id' => $campaign->id,
                        'recipient_id' => $recipient->getKey(),
                        'exception' => $exception::class,
                    ]);
                }
            }
        });

        $campaign->refresh()->update([
            'status' => 'completed',
            'completed_at' => now(),
            'created_count' => $campaign->created_count + $created,
            'failed_count' => $campaign->failed_count + $failed,
        ]);
    }
}
