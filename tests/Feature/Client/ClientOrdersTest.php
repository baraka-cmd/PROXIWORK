<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\OrderStatus;
use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_is_redirected_to_login_for_the_orders_area(): void
    {
        $this->get('/client/orders')->assertRedirect('/login');
    }

    public function test_non_client_cannot_open_the_client_orders_area(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)->get('/client/orders')->assertForbidden();
    }

    public function test_orders_dashboard_counts_and_lists_only_the_authenticated_clients_orders(): void
    {
        $client = $this->client();
        $otherClient = $this->client();

        $ownedOrder = $this->makeOrder($client, 'Réparation ordinateur portable', OrderStatus::PENDING_PAYMENT);
        $this->makeOrder($client, 'Installation réseau local', OrderStatus::IN_PROGRESS);
        $this->makeOrder($otherClient, 'Commande privée d’un autre client', OrderStatus::COMPLETED);

        $response = $this->actingAs($client)
            ->get('/client/orders')
            ->assertOk()
            ->assertViewIs('client.orders.index')
            ->assertSee($ownedOrder->order_number)
            ->assertSee('Réparation ordinateur portable')
            ->assertSee('Installation réseau local')
            ->assertDontSee('Commande privée d’un autre client');

        $response->assertViewHas('statistics', function (array $statistics): bool {
            return $statistics['total'] === 2
                && $statistics['active'] === 1
                && $statistics['pending_payment'] === 1
                && $statistics['completed'] === 0;
        });
    }

    public function test_client_can_filter_orders_by_status_and_search_service_name(): void
    {
        $client = $this->client();

        $pending = $this->makeOrder($client, 'Réparation ordinateur portable', OrderStatus::PENDING_PAYMENT);
        $this->makeOrder($client, 'Installation réseau local', OrderStatus::IN_PROGRESS);

        $response = $this->actingAs($client)
            ->get('/client/orders?status=pending_payment&q=ordinateur')
            ->assertOk()
            ->assertSee($pending->order_number)
            ->assertSee('Réparation ordinateur portable');

        $response->assertViewHas('orders', fn ($orders): bool => $orders->total() === 1
            && $orders->first()?->getKey() === $pending->getKey());
    }

    public function test_client_can_filter_orders_by_the_quick_active_bucket(): void
    {
        $client = $this->client();

        $this->makeOrder($client, 'Commande en attente de paiement', OrderStatus::PENDING_PAYMENT);
        $activeOrder = $this->makeOrder($client, 'Commande en cours', OrderStatus::IN_PROGRESS);
        $this->makeOrder($client, 'Commande terminée', OrderStatus::COMPLETED);

        $response = $this->actingAs($client)
            ->get('/client/orders?bucket=active')
            ->assertOk()
            ->assertSee($activeOrder->order_number)
            ->assertSee('Commande en cours');

        $response->assertViewHas('orders', fn ($orders): bool => $orders->total() === 1
            && $orders->first()?->getKey() === $activeOrder->getKey());
    }

    public function test_order_filters_reject_unknown_status_and_invalid_date_range(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get('/client/orders?status=paid')
            ->assertSessionHasErrors('status');

        $this->actingAs($client)
            ->get('/client/orders?created_from=2026-10-10&created_to=2026-10-01')
            ->assertSessionHasErrors('created_to');
    }

    public function test_client_can_open_only_their_own_order_detail(): void
    {
        $owner = $this->client();
        $otherClient = $this->client();
        $order = $this->makeOrder($owner, 'Commande privée du propriétaire', OrderStatus::PENDING_PAYMENT);

        $this->actingAs($owner)
            ->get('/client/orders/'.$order->id)
            ->assertOk()
            ->assertViewIs('client.orders.show')
            ->assertSee('Commande privée du propriétaire');

        $this->actingAs($otherClient)
            ->get('/client/orders/'.$order->id)
            ->assertForbidden();
    }

    private function makeOrder(User $client, string $serviceTitle, OrderStatus $status): Order
    {
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');

        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);

        $service = Service::factory()->create([
            'professional_profile_id' => $professional->id,
            'title' => $serviceTitle,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $address = Address::factory()->create([
            'user_id' => $client->id,
        ]);

        $serviceRequest = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'address_id' => $address->id,
            'status' => ServiceRequestStatus::ACCEPTED,
        ]);

        $quotation = Quotation::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'status' => QuotationStatus::ACCEPTED,
            'accepted_at' => now(),
        ]);

        $offer = QuotationOffer::factory()->create([
            'quotation_id' => $quotation->id,
            'created_by' => $professionalUser->id,
            'actor_type' => QuotationOfferActor::PROFESSIONAL->value,
            'amount' => 150,
            'currency' => 'USD',
            'description' => 'Prestation convenue et conservée dans la commande.',
            'conditions' => 'Conditions particulières convenues.',
        ]);

        $quotation->forceFill([
            'current_offer_id' => $offer->id,
            'accepted_offer_id' => $offer->id,
        ])->save();

        $order = app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client);

        if ($status !== OrderStatus::PENDING_PAYMENT) {
            $order->forceFill([
                'status' => $status,
                'confirmed_at' => in_array($status, [OrderStatus::CONFIRMED, OrderStatus::IN_PROGRESS, OrderStatus::COMPLETED], true) ? now() : null,
                'started_at' => in_array($status, [OrderStatus::IN_PROGRESS, OrderStatus::COMPLETED], true) ? now() : null,
                'completed_at' => $status === OrderStatus::COMPLETED ? now() : null,
            ])->save();
        }

        return $order->refresh();
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }
}
