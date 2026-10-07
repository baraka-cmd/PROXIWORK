<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Moderation;

use App\Models\{Report, Service, User};
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_can_create_report_for_service(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        $service = Service::factory()->create();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/reports', [
                'target_type' => 'service',
                'target_id' => $service->id,
                'reason_code' => 'SCAM',
                'description' => 'Contenu suspect',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $client->id,
            'target_id' => $service->id,
            'target_type' => 'App\\Models\\Service',
        ]);
    }

    public function test_client_cannot_access_admin_reports(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/admin/reports')
            ->assertForbidden();
    }

    public function test_moderator_cannot_assign_report_to_unauthorized_user(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $unauthorized = User::factory()->create();
        $unauthorized->assignRole('client');

        $report = Report::create([
            'reporter_id' => $client->id,
            'target_type' => 'App\\Models\\Service',
            'target_id' => Service::factory()->create()->id,
            'reason_code' => 'SCAM',
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/v1/admin/reports/{$report->id}/assign", [
                'assigned_to' => $unauthorized->id,
            ])
            ->assertUnprocessable();
    }

    public function test_moderator_can_review_and_unpublish_service(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $service = Service::factory()->create();
        $report = Report::create([
            'reporter_id' => $client->id,
            'target_type' => 'App\\Models\\Service',
            'target_id' => $service->id,
            'reason_code' => 'SCAM',
        ]);

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/v1/admin/reports/{$report->id}/start-review")
            ->assertOk();

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/v1/admin/reports/{$report->id}/actions", [
                'action_type' => 'unpublish_service',
                'reason_code' => 'SCAM',
                'note' => 'Service retiré',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => 'unpublished',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'moderation.action_created',
        ]);
    }
}
