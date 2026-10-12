<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Enums\ServiceExecutionMode;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceExecutionModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_remote_service_request_can_be_submitted_without_an_address(): void
    {
        $client = $this->client();
        $service = $this->publishedService(ServiceExecutionMode::REMOTE);

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests', [
                'service_id' => $service->id,
                'title' => 'Consultation informatique à distance',
                'description' => 'Je souhaite obtenir une consultation informatique par visioconférence.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.execution_mode', ServiceExecutionMode::REMOTE->value)
            ->assertJsonPath('data.address', null);

        $requestId = $response->json('data.id');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$requestId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.execution_mode', ServiceExecutionMode::REMOTE->value)
            ->assertJsonPath('data.address', null);

        $this->assertDatabaseHas('service_requests', [
            'id' => $requestId,
            'client_id' => $client->id,
            'execution_mode' => ServiceExecutionMode::REMOTE->value,
            'address_id' => null,
            'status' => 'requested',
        ]);

        $this->assertDatabaseMissing('service_request_address_snapshots', [
            'service_request_id' => $requestId,
        ]);
    }

    public function test_client_location_service_cannot_be_submitted_without_an_address(): void
    {
        $client = $this->client();
        $service = $this->publishedService(ServiceExecutionMode::CLIENT_LOCATION);

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests', [
                'service_id' => $service->id,
                'title' => 'Réparation à domicile',
                'description' => 'Une intervention physique est nécessaire dans mon logement.',
            ])
            ->assertCreated();

        $requestId = $response->json('data.id');

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$requestId.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id']);

        $this->assertDatabaseHas('service_requests', [
            'id' => $requestId,
            'execution_mode' => ServiceExecutionMode::CLIENT_LOCATION->value,
            'address_id' => null,
            'status' => 'draft',
        ]);
    }

    private function client(): User
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        return $client;
    }

    private function publishedService(ServiceExecutionMode $mode): Service
    {
        return Service::factory()->create([
            'execution_mode' => $mode,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ])->fresh(['category', 'professionalProfile.user']);
    }
}
