<?php

declare(strict_types=1);

namespace Tests\Feature\ServiceRequest;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Notification::fake();
    }

    public function test_client_can_create_a_draft_request_for_a_published_service(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $client = $this->client();

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests', [
                'service_id' => $service->id,
                'title' => 'Je cherche un développeur Laravel',
                'description' => 'Je souhaite développer une application web complète avec Laravel.',
                'budget_min' => 200,
                'budget_max' => 500,
                'currency' => 'usd',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.service.id', $service->id)
            ->assertJsonPath('data.professional.id', $profile->id);

        $this->assertDatabaseHas('service_requests', [
            'id' => $response->json('data.id'),
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::DRAFT->value,
        ]);
    }

    public function test_client_cannot_create_request_for_unpublished_service(): void
    {
        [$professional, $profile] = $this->professional();
        $service = Service::factory()->create(['professional_profile_id' => $profile->id]);
        $client = $this->client();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests', [
                'service_id' => $service->id,
                'title' => 'Demande invalide',
                'description' => 'Cette demande ne doit pas pouvoir cibler un service non publié.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
    }

    public function test_professional_cannot_create_request(): void
    {
        [$professional] = $this->professional();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests', [])
            ->assertForbidden();
    }

    public function test_client_can_update_only_a_draft_request(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $client = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->patchJson('/api/v1/service-requests/'.$request->id, [
                'title' => 'Titre modifié',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Titre modifié');

        $request->forceFill(['status' => ServiceRequestStatus::REQUESTED])->save();

        $this->actingAs($client, 'sanctum')
            ->patchJson('/api/v1/service-requests/'.$request->id, [
                'title' => 'Modification interdite',
            ])
            ->assertForbidden();
    }

    public function test_submit_creates_requested_history_and_notifies_professional(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $client = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'requested');

        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'status' => ServiceRequestStatus::REQUESTED->value,
        ]);

        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $request->id,
            'from_status' => 'draft',
            'to_status' => 'requested',
            'changed_by' => $client->id,
        ]);

        Notification::assertSentTo($professional, AccountActivityNotification::class);
    }

    public function test_client_cannot_submit_twice_or_submit_another_clients_request(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $clientA = $this->client();
        $clientB = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $clientA->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
        ]);

        $this->actingAs($clientB, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/submit')
            ->assertForbidden();

        $this->actingAs($clientA, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/submit')
            ->assertOk();

        $this->actingAs($clientA, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/submit')
            ->assertUnprocessable();
    }

    public function test_client_can_cancel_draft_or_requested_but_not_terminal_request(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $client = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $request->id,
            'from_status' => 'draft',
            'to_status' => 'cancelled',
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/cancel')
            ->assertForbidden();
    }

    public function test_target_professional_can_reject_requested_request(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $client = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::REQUESTED,
            'requested_at' => now(),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/reject', [
                'reason' => 'Le délai demandé n’est pas compatible avec mes disponibilités.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $request->id,
            'from_status' => 'requested',
            'to_status' => 'rejected',
            'changed_by' => $professional->id,
            'reason' => 'Le délai demandé n’est pas compatible avec mes disponibilités.',
        ]);

        Notification::assertSentTo($client, AccountActivityNotification::class);
    }

    public function test_other_professional_cannot_view_or_reject_request(): void
    {
        [$professionalA, $profileA] = $this->professional();
        [$professionalB] = $this->professional();
        $service = $this->publishedService($profileA);
        $client = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $profileA->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::REQUESTED,
            'requested_at' => now(),
        ]);

        $this->actingAs($professionalB, 'sanctum')
            ->getJson('/api/v1/service-requests/'.$request->id)
            ->assertForbidden();

        $this->actingAs($professionalB, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/reject')
            ->assertForbidden();
    }

    public function test_client_and_professional_lists_are_isolated_and_paginated(): void
    {
        [$professionalA, $profileA] = $this->professional();
        [$professionalB, $profileB] = $this->professional();
        $clientA = $this->client();
        $clientB = $this->client();

        $serviceA = $this->publishedService($profileA);
        $serviceB = $this->publishedService($profileB);

        ServiceRequest::factory()->count(2)->create([
            'client_id' => $clientA->id,
            'professional_id' => $profileA->id,
            'service_id' => $serviceA->id,
        ]);
        ServiceRequest::factory()->create([
            'client_id' => $clientB->id,
            'professional_id' => $profileB->id,
            'service_id' => $serviceB->id,
        ]);

        $this->actingAs($clientA, 'sanctum')
            ->getJson('/api/v1/service-requests?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($professionalA, 'sanctum')
            ->getJson('/api/v1/professional/service-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($professionalB, 'sanctum')
            ->getJson('/api/v1/professional/service-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_lifecycle_supports_quoted_and_accepted_without_exposing_arbitrary_status_update(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $client = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::REQUESTED,
            'requested_at' => now(),
        ]);

        app(\App\Services\ServiceRequest\ServiceRequestLifecycleService::class)
            ->markQuoted($request, $professional);

        $request->refresh();
        $this->assertSame(ServiceRequestStatus::QUOTED, $request->status);

        app(\App\Services\ServiceRequest\ServiceRequestLifecycleService::class)
            ->accept($request, $client);

        $this->assertSame(ServiceRequestStatus::ACCEPTED, $request->refresh()->status);

        $this->actingAs($client, 'sanctum')
            ->patchJson('/api/v1/service-requests/'.$request->id, [
                'status' => 'requested',
                'title' => 'Modification autorisée du contenu',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.title', 'Modification autorisée du contenu');
    }

    public function test_status_history_is_returned_on_show(): void
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $client = $this->client();

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/submit')
            ->assertOk();

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/service-requests/'.$request->id)
            ->assertOk()
            ->assertJsonPath('data.status_history.0.from_status', 'draft')
            ->assertJsonPath('data.status_history.0.to_status', 'requested');
    }

    public function test_guest_cannot_access_service_requests(): void
    {
        $this->getJson('/api/v1/service-requests')->assertUnauthorized();
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    private function professional(): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        return [$user, $profile];
    }

    private function publishedService(ProfessionalProfile $profile): Service
    {
        $category = Category::factory()->create();

        return Service::factory()->create([
            'professional_profile_id' => $profile->id,
            'category_id' => $category->id,
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
