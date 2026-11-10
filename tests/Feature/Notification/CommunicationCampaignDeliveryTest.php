<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Jobs\SendCommunicationCampaign;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSegment;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationCampaignDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_freezes_recipients_from_a_saved_segment_before_campaign_queueing(): void
    {
        $this->seed(RbacSeeder::class);
        Queue::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $client = User::factory()->create(['email_verified_at' => now()]);
        $client->assignRole('client');
        $segment = CommunicationSegment::query()->create([
            'name' => 'Clients actifs',
            'rules' => ['audience' => 'client', 'account_status' => 'active'],
            'created_by' => $admin->id,
        ]);
        $campaign = CommunicationCampaign::query()->create([
            'name' => 'Information ciblée',
            'subject' => 'Mise à jour',
            'body' => 'Une information est disponible.',
            'channel' => 'database',
            'audience_rules' => $segment->rules,
            'communication_segment_id' => $segment->id,
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.communication.campaigns.dispatch', $campaign))
            ->assertRedirect();

        $this->assertDatabaseHas('communication_campaigns', [
            'id' => $campaign->id,
            'status' => 'queued',
            'recipient_count' => 1,
        ]);
        $this->assertDatabaseHas('communication_campaign_recipients', [
            'campaign_id' => $campaign->id,
            'user_id' => $client->id,
            'status' => 'pending',
        ]);
    }

    public function test_scheduler_queues_campaigns_when_their_scheduled_time_is_due(): void
    {
        Queue::fake();
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
        Queue::assertPushed(SendCommunicationCampaign::class);
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
