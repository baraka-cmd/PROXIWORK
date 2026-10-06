<?php

declare(strict_types=1);

namespace Tests\Feature\Quotation;

use App\Enums\OrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuotationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        Notification::fake();
    }

    public function test_target_professional_can_create_only_one_quotation_for_requested_request(): void
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $payload = $this->offerPayload();

        $response = $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.current_offer.version', 1)
            ->assertJsonPath('data.current_offer.amount', '500.00');

        $this->assertDatabaseHas('quotations', [
            'id' => $response->json('data.id'),
            'service_request_id' => $request->id,
            'status' => QuotationStatus::SENT->value,
        ]);
        $this->assertDatabaseHas('quotation_offers', [
            'quotation_id' => $response->json('data.id'),
            'version' => 1,
            'amount' => 500,
            'created_by' => $professional->id,
        ]);
        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'status' => ServiceRequestStatus::QUOTED->value,
        ]);
        Notification::assertSentTo($client, AccountActivityNotification::class);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $payload)
            ->assertUnprocessable();
    }

    public function test_wrong_professional_cannot_create_or_view_quotation(): void
    {
        [$professional, $profile] = $this->professional();
        [$otherProfessional] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $this->actingAs($otherProfessional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $this->offerPayload())
            ->assertForbidden();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $this->offerPayload())
            ->assertCreated();

        $quotation = Quotation::query()->firstOrFail();

        $this->actingAs($otherProfessional, 'sanctum')
            ->getJson('/api/v1/quotations/'.$quotation->id)
            ->assertForbidden();
    }

    public function test_client_can_counter_offer_and_previous_offer_remains_immutable(): void
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $this->offerPayload())
            ->assertCreated();

        $quotation = Quotation::query()->firstOrFail();
        $firstOffer = $quotation->current_offer_id;

        $counter = $this->offerPayload();
        $counter['amount'] = 400;

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/offers', $counter)
            ->assertOk()
            ->assertJsonPath('data.status', 'negotiating')
            ->assertJsonPath('data.current_offer.version', 2)
            ->assertJsonPath('data.current_offer.amount', '400.00');

        $this->assertDatabaseHas('quotation_offers', [
            'id' => $firstOffer,
            'version' => 1,
            'amount' => 500,
        ]);
        $this->assertDatabaseHas('quotation_offers', [
            'quotation_id' => $quotation->id,
            'version' => 2,
            'amount' => 400,
            'created_by' => $client->id,
        ]);
        $this->assertDatabaseCount('quotation_offers', 2);
        Notification::assertSentTo($professional, AccountActivityNotification::class);
    }

    public function test_only_the_current_non_expired_offer_can_be_accepted(): void
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $this->offerPayload())
            ->assertCreated();

        $quotation = Quotation::query()->firstOrFail();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $quotation->refresh();
        $this->assertNotNull($quotation->accepted_offer_id);
        $this->assertNotNull($quotation->accepted_at);
        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'status' => ServiceRequestStatus::ACCEPTED->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'service_request_id' => $request->id,
            'quotation_id' => $quotation->id,
            'accepted_offer_id' => $quotation->accepted_offer_id,
            'client_id' => $client->id,
            'professional_id' => $profile->id,
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'currency' => 'USD',
            'total' => 500,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->app->make(\\App\\Models\\Order::class)::query()->where('quotation_id', $quotation->id)->value('id'),
            'service_id' => $service->id,
            'unit_price' => 500,
            'subtotal' => 500,
            'quantity' => 1,
        ]);
        Notification::assertSentTo($professional, AccountActivityNotification::class);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/accept')
            ->assertUnprocessable();
    }

    public function test_order_address_is_a_historical_snapshot(): void
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $this->offerPayload())
            ->assertCreated();

        $quotation = Quotation::query()->firstOrFail();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/accept')
            ->assertOk();

        $order = \\App\\Models\\Order::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $snapshot = $order->addressSnapshot;

        $address = Address::query()->findOrFail($request->address_id);
        $originalCity = $snapshot->city;

        $address->forceFill([
            'city' => 'Bukavu',
            'province' => 'Sud-Kivu',
            'address_line_1' => 'Nouvelle adresse',
        ])->save();

        $this->assertSame($originalCity, $snapshot->fresh()->city);
        $this->assertSame('Goma', $snapshot->fresh()->city);
        $this->assertSame('Nord-Kivu', $snapshot->fresh()->province);
        $this->assertSame($address->address_line_1 !== 'Nouvelle adresse', true);
    }

    public function test_order_is_isolated_from_unrelated_users(): void
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $otherClient = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $this->offerPayload())
            ->assertCreated();

        $quotation = Quotation::query()->firstOrFail();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/accept')
            ->assertOk();

        $order = \\App\\Models\\Order::query()->where('quotation_id', $quotation->id)->firstOrFail();

        $this->actingAs($otherClient, 'sanctum')
            ->getJson('/api/v1/orders/'.$order->id)
            ->assertForbidden();
    }

    public function test_expired_current_offer_cannot_be_accepted_or_negotiated(): void
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $payload = $this->offerPayload();
        $payload['valid_until'] = now()->subMinute()->toISOString();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $payload)
            ->assertUnprocessable();

        $payload['valid_until'] = now()->addDay()->toISOString();

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $payload)
            ->assertCreated();

        $quotation = Quotation::query()->firstOrFail();
        QuotationOffer::query()->whereKey($quotation->current_offer_id)->update([
            'valid_until' => now()->subMinute(),
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/accept')
            ->assertUnprocessable();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/offers', $this->offerPayload())
            ->assertUnprocessable();
    }

    public function test_client_can_reject_quotation_and_request_becomes_rejected(): void
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $request = $this->request($client, $profile, $service);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/service-requests/'.$request->id.'/quotation', $this->offerPayload())
            ->assertCreated();

        $quotation = Quotation::query()->firstOrFail();

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/quotations/'.$quotation->id.'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'status' => ServiceRequestStatus::REJECTED->value,
        ]);
        $this->assertDatabaseHas('quotation_events', [
            'quotation_id' => $quotation->id,
            'type' => 'quote_rejected',
            'actor_id' => $client->id,
        ]);
        Notification::assertSentTo($professional, AccountActivityNotification::class);
    }

    public function test_guest_cannot_access_quotation_api(): void
    {
        $this->getJson('/api/v1/quotations/1')->assertUnauthorized();
    }

    private function offerPayload(): array
    {
        return [
            'amount' => 500,
            'currency' => 'USD',
            'description' => 'Développement complet selon les besoins décrits dans la demande.',
            'duration_value' => 10,
            'duration_unit' => 'days',
            'conditions' => '50% au démarrage et 50% à la livraison.',
            'valid_until' => now()->addDays(5)->toISOString(),
        ];
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

    private function request(User $client, ProfessionalProfile $profile, Service $service): ServiceRequest
    {
        $address = Address::factory()->default()->create([
            'user_id' => $client->id,
        ]);

        return ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'address_id' => $address->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::REQUESTED,
            'requested_at' => now(),
        ]);
    }
}
