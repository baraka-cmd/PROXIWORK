<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAddressSnapshot;
use App\Models\User;
use App\Services\AddressService;
use App\Services\ServiceRequest\ServiceRequestService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestAddressSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_address_book_edits_do_not_rewrite_an_existing_request_location(): void
    {
        $client = $this->client();
        $address = Address::factory()->for($client)->create([
            'city' => 'Goma',
            'access_instructions' => 'Portail bleu.',
        ]);
        $service = $this->requestableService();

        $request = app(ServiceRequestService::class)->create($client, [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'title' => 'Réparer une installation électrique',
            'description' => 'Une intervention est nécessaire dans le logement principal.',
        ]);

        $snapshot = $request->addressSnapshot()->firstOrFail();

        $this->assertSame('Goma', $snapshot->city);
        $this->assertSame('Portail bleu.', $snapshot->access_instructions);

        $address->update([
            'city' => 'Bukavu',
            'access_instructions' => 'Entrée arrière.',
        ]);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'city' => 'Bukavu',
        ]);
        $this->assertDatabaseHas('service_request_address_snapshots', [
            'service_request_id' => $request->id,
            'city' => 'Goma',
            'access_instructions' => 'Portail bleu.',
        ]);
    }

    public function test_explicit_pre_acceptance_address_change_replaces_the_request_snapshot(): void
    {
        $client = $this->client();
        $firstAddress = Address::factory()->for($client)->create([
            'city' => 'Goma',
            'access_instructions' => 'Première entrée.',
        ]);
        $secondAddress = Address::factory()->for($client)->create([
            'city' => 'Bukavu',
            'access_instructions' => 'Entrée côté avenue.',
        ]);
        $service = $this->requestableService();

        $request = app(ServiceRequestService::class)->create($client, [
            'service_id' => $service->id,
            'address_id' => $firstAddress->id,
            'title' => 'Installer un équipement',
            'description' => 'Une installation doit être réalisée dans un autre logement.',
        ]);
        $request->forceFill(['status' => ServiceRequestStatus::REQUESTED])->save();

        $updated = app(ServiceRequestService::class)->setAddress(
            $request,
            $client,
            $secondAddress->id,
        );

        $this->assertSame($secondAddress->id, $updated->address_id);
        $this->assertSame('Bukavu', $updated->addressSnapshot->city);
        $this->assertSame('Entrée côté avenue.', $updated->addressSnapshot->access_instructions);
        $this->assertSame(1, ServiceRequestAddressSnapshot::query()
            ->where('service_request_id', $request->id)
            ->count());
    }

    public function test_historical_request_keeps_its_snapshot_when_its_address_is_deleted(): void
    {
        $client = $this->client();
        $address = Address::factory()->for($client)->create([
            'city' => 'Goma',
            'access_instructions' => 'Appeler à l’arrivée.',
        ]);
        $service = $this->requestableService();

        $request = app(ServiceRequestService::class)->create($client, [
            'service_id' => $service->id,
            'address_id' => $address->id,
            'title' => 'Réparer une porte',
            'description' => 'La porte d’entrée nécessite une réparation et un réglage.',
        ]);
        $request->forceFill(['status' => ServiceRequestStatus::REJECTED])->save();

        app(AddressService::class)->delete($client, $address);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'address_id' => null,
        ]);
        $this->assertDatabaseHas('service_request_address_snapshots', [
            'service_request_id' => $request->id,
            'city' => 'Goma',
            'access_instructions' => 'Appeler à l’arrivée.',
        ]);
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    private function requestableService(): Service
    {
        $service = Service::factory()->create([
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $service->category()->update(['status' => 'active']);

        return $service->fresh(['category', 'professionalProfile.user']);
    }
}
