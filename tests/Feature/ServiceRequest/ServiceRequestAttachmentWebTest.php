<?php

declare(strict_types=1);

namespace Tests\Feature\ServiceRequest;

use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttachment;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceRequestAttachmentWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Storage::fake('local');
    }

    public function test_client_can_upload_a_private_attachment_and_the_assigned_professional_can_download_it(): void
    {
        [$client, $professional, $serviceRequest] = $this->makeRequest(ServiceRequestStatus::REQUESTED);

        $this->actingAs($client)
            ->post(route('client.requests.attachments.store', $serviceRequest), [
                'attachment' => UploadedFile::fake()->create('network-plan.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('client.requests.show', $serviceRequest));

        $attachment = ServiceRequestAttachment::query()->where('service_request_id', $serviceRequest->getKey())->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame($client->getKey(), $attachment->uploaded_by);
        $this->assertSame(64, strlen($attachment->sha256));

        $this->actingAs($professional)
            ->get(route('professional.requests.attachments.download', [$serviceRequest, $attachment]))
            ->assertOk()
            ->assertDownload('network-plan.pdf');
    }

    public function test_other_clients_and_professionals_cannot_access_private_request_attachments(): void
    {
        [$client, $professional, $serviceRequest] = $this->makeRequest(ServiceRequestStatus::REQUESTED);
        $attachment = $this->createAttachment($client, $serviceRequest, 'network-plan.pdf');
        $otherClient = $this->client();
        $otherProfessional = $this->professional();

        $this->actingAs($otherClient)
            ->get(route('client.requests.attachments.download', [$serviceRequest, $attachment]))
            ->assertForbidden();

        $this->actingAs($otherProfessional)
            ->get(route('professional.requests.attachments.download', [$serviceRequest, $attachment]))
            ->assertForbidden();

        [, , $otherRequest] = $this->makeRequest(ServiceRequestStatus::REQUESTED, $client, $professional);
        $this->actingAs($client)
            ->get(route('client.requests.attachments.download', [$otherRequest, $attachment]))
            ->assertNotFound();

        $this->actingAs($otherClient)
            ->post(route('client.requests.attachments.store', $serviceRequest), [
                'attachment' => UploadedFile::fake()->create('unauthorized.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_upload_validation_rejects_unsupported_files_and_more_than_five_attachments(): void
    {
        [$client, , $serviceRequest] = $this->makeRequest(ServiceRequestStatus::REQUESTED);

        $this->actingAs($client)
            ->from(route('client.requests.show', $serviceRequest))
            ->post(route('client.requests.attachments.store', $serviceRequest), [
                'attachment' => UploadedFile::fake()->create('program.exe', 100, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('attachment');

        for ($index = 1; $index <= 5; $index++) {
            $this->createAttachment($client, $serviceRequest, 'document-'.$index.'.pdf');
        }

        $this->actingAs($client)
            ->from(route('client.requests.show', $serviceRequest))
            ->post(route('client.requests.attachments.store', $serviceRequest), [
                'attachment' => UploadedFile::fake()->create('sixth-document.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('service_request_attachments', 5);
    }

    public function test_client_cannot_add_attachments_after_quote_evaluation_has_started(): void
    {
        [$client, , $serviceRequest] = $this->makeRequest(ServiceRequestStatus::QUOTED);
        Quotation::factory()->create([
            'service_request_id' => $serviceRequest->getKey(),
            'status' => QuotationStatus::SENT,
        ]);

        $this->actingAs($client)
            ->from(route('client.requests.show', $serviceRequest))
            ->post(route('client.requests.attachments.store', $serviceRequest), [
                'attachment' => UploadedFile::fake()->create('late-document.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('service_request_attachments', 0);
    }

    private function makeRequest(ServiceRequestStatus $status, ?User $client = null, ?User $professional = null): array
    {
        $client ??= $this->client();
        $professional ??= $this->professional();
        $profile = $professional->professionalProfile()->firstOrFail();
        $service = Service::factory()->create(['professional_profile_id' => $profile->getKey()]);
        $serviceRequest = ServiceRequest::factory()->create([
            'client_id' => $client->getKey(),
            'professional_id' => $profile->getKey(),
            'service_id' => $service->getKey(),
            'status' => $status,
            'requested_at' => now(),
        ]);

        return [$client, $professional, $serviceRequest];
    }

    private function createAttachment(User $client, ServiceRequest $serviceRequest, string $name): ServiceRequestAttachment
    {
        $path = UploadedFile::fake()->create($name, 100, 'application/pdf')->store(
            'service-request-attachments/'.$serviceRequest->getKey(),
            'local',
        );

        return $serviceRequest->attachments()->create([
            'uploaded_by' => $client->getKey(),
            'path' => $path,
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size' => 102400,
            'sha256' => hash('sha256', $name),
        ]);
    }

    private function professional(): User
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->getKey()]);

        return $user;
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }
}
