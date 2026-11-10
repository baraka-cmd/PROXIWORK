<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Jobs\SendCommunicationCampaign;
use App\Models\CommunicationCampaign;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommunicationCampaignDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_queues_campaigns_when_their_scheduled_time_is_due(): void
    {
        $campaign = CommunicationCampaign::query()->create([
            'name' => 'Campagne programmée',
            'subject' => 'Information',
            'body' => 'Message planifié.',
            'channel' => 'database',
            'audience_rules' => ['audience' => 'all', 'account_status' => 'active'],
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'recipient_count' => 1,
        ]);

        Artisan::call('communication:dispatch-scheduled');

        $this->assertDatabaseHas('communication_campaigns', [
            'id' => $campaign->id,
            'status' => 'queued',
        ]);
    }

    public function test_campaign_job_delivers_internal_notification_once_per_snapshotted_recipient(): void
    {
        $this->seed(RbacSeeder::class);
        $client = User::factory()->create(['email_verified_at' => now()]);
        $client->assignRole('client');

        $campaign = CommunicationCampaign::query()->create([
            'name' => 'Information de service',
            'subject' => 'Mise à jour PROXIWORK',
            'body' => 'Une nouvelle information est disponible.',
            'channel' => 'database',
            'audience_rules' => ['audience' => 'client', 'account_status' => 'active'],
            'status' => 'queued',
            'recipient_count' => 1,
        ]);
        $campaign->recipients()->create(['user_id' => $client->id, 'status' => 'pending']);

        $job = new SendCommunicationCampaign($campaign->id);
        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('communication_campaign_recipients', [
            'campaign_id' => $campaign->id,
            'user_id' => $client->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('communication_campaigns', [
            'id' => $campaign->id,
            'status' => 'completed',
            'sent_count' => 1,
        ]);
        $this->assertCount(1, $client->fresh()->notifications);
    }
}
