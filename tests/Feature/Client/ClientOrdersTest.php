<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\QuotationOffer;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_unrelated_professional_cannot_propose_a_schedule_for_another_professionals_order(): void
    {
        $client = $this->client();
        $order = $this->makeOrder($client, 'Installation réseau', OrderStatus::CONFIRMED);
        $unrelatedProfessional = User::factory()->create();
        $unrelatedProfessional->assignRole('professional');

        $this->actingAs($unrelatedProfessional)
            ->post(route('professional.orders.schedule', $order), [
                'starts_at' => now()->addDays(3)->startOfHour()->format('Y-m-d\\TH:i'),
                'ends_at' => now()->addDays(3)->startOfHour()->addHours(2)->format('Y-m-d\\TH:i'),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('order_status_histories', [
            'order_id' => $order->getKey(),
            'reason' => 'Le professionnel a proposé un planning.',
        ]);
    }

    public function test_only_the_order_client_can_confirm_or_change_its_schedule(): void
    {
        $client = $this->client();
        $order = $this->makeOrder($client, 'Maintenance informatique', OrderStatus::CONFIRMED);
        $professional = $order->professional->user;
        $unrelatedClient = $this->client();
        $startsAt = now()->addDays(4)->startOfHour();

        $this->actingAs($professional)
            ->post(route('professional.orders.schedule', $order), [
                'starts_at' => $startsAt->format('Y-m-d\\TH:i'),
                'ends_at' => $startsAt->copy()->addHours(3)->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect(route('professional.orders.show', $order));

        $this->actingAs($unrelatedClient)
            ->post(route('client.orders.confirm-schedule', $order))
            ->assertForbidden();

        $this->actingAs($unrelatedClient)
            ->post(route('client.orders.request-schedule-change', $order), [
                'reason' => 'Je souhaite modifier cette date de rendez-vous.',
            ])
            ->assertForbidden();

        $this->assertNull($order->fresh()->schedule_confirmed_at);
        $this->assertDatabaseMissing('order_status_histories', [
            'order_id' => $order->getKey(),
            'reason' => 'Le client a confirmé le planning proposé.',
        ]);
    }

    public function test_professional_proposes_schedule_and_client_must_confirm_it(): void
    {
        $client = $this->client();
        $order = $this->makeOrder($client, 'Installation réseau', OrderStatus::CONFIRMED);
        $professional = $order->professional->user;
        $startsAt = now()->addDays(3)->startOfHour();
        $endsAt = $startsAt->copy()->addHours(4);

        $this->actingAs($professional)
            ->post(route('professional.orders.schedule', $order), [
                'starts_at' => $startsAt->format('Y-m-d\\TH:i'),
                'ends_at' => $endsAt->format('Y-m-d\\TH:i'),
                'note' => 'Merci de garantir l’accès au local technique.',
            ])
            ->assertRedirect(route('professional.orders.show', $order));

        $this->assertNull($order->fresh()->scheduled_start_at);
        $this->assertNull($order->fresh()->schedule_confirmed_at);

        $this->actingAs($professional)
            ->from(route('professional.orders.show', $order))
            ->post(route('professional.orders.schedule', $order), [
                'starts_at' => now()->addDays(6)->startOfHour()->format('Y-m-d\\TH:i'),
                'ends_at' => now()->addDays(6)->startOfHour()->addHours(2)->format('Y-m-d\\TH:i'),
            ])
            ->assertSessionHasErrors('schedule');

        $this->assertSame(1, $order->statusHistories()->where('reason', 'Le professionnel a proposé un planning.')->count());

        $this->actingAs($client)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee('PLANIFICATION À CONFIRMER')
            ->assertSee('Confirmer le planning');

        $this->actingAs($client)
            ->post(route('client.orders.confirm-schedule', $order))
            ->assertRedirect(route('client.orders.show', $order));

        $updated = $order->fresh();
        $this->assertTrue($updated->scheduled_start_at->equalTo($startsAt));
        $this->assertTrue($updated->scheduled_end_at->equalTo($endsAt));
        $this->assertNotNull($updated->schedule_confirmed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::CONFIRMED->value,
            'to_status' => OrderStatus::CONFIRMED->value,
            'reason' => 'Le client a confirmé le planning proposé.',
        ]);
    }

    public function test_client_can_request_schedule_changes_without_losing_the_schedule_history(): void
    {
        $client = $this->client();
        $order = $this->makeOrder($client, 'Maintenance informatique', OrderStatus::CONFIRMED);
        $professional = $order->professional->user;
        $startsAt = now()->addDays(5)->startOfHour();
        $endsAt = $startsAt->copy()->addHours(2);

        $this->actingAs($professional)
            ->post(route('professional.orders.schedule', $order), [
                'starts_at' => $startsAt->format('Y-m-d\\TH:i'),
                'ends_at' => $endsAt->format('Y-m-d\\TH:i'),
            ])
            ->assertRedirect(route('professional.orders.show', $order));

        $this->actingAs($client)
            ->post(route('client.orders.request-schedule-change', $order), [
                'reason' => 'Je ne serai pas disponible à ces dates, merci de proposer une autre période.',
            ])
            ->assertRedirect(route('client.orders.show', $order));

        $updated = $order->fresh();
        $this->assertNull($updated->scheduled_start_at);
        $this->assertNull($updated->scheduled_end_at);
        $this->assertNull($updated->schedule_confirmed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'reason' => 'Le client a demandé une modification du planning.',
        ]);

        $this->actingAs($professional)
            ->get(route('professional.orders.show', $order))
            ->assertOk()
            ->assertSee('Proposer un planning au client');
    }

    public function test_professional_can_submit_private_delivery_files_and_client_can_confirm_receipt(): void
    {
        Storage::fake('local');

        $client = $this->client();
        $order = $this->makeOrder($client, 'Développement application web', OrderStatus::IN_PROGRESS);
        $professional = $order->professional->user;

        $this->actingAs($professional)
            ->post(route('professional.orders.submit-delivery', $order), [
                'summary' => 'Le travail demandé est terminé. Le document et les éléments livrés sont joints pour vérification.',
                'attachments' => [
                    UploadedFile::fake()->create('livrable.pdf', 25, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('professional.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::DELIVERY_SUBMITTED, $order->status);
        $this->assertDatabaseCount('order_delivery_attachments', 1);

        $attachment = $order->deliveryAttachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $unrelatedClient = $this->client();
        $this->actingAs($unrelatedClient)
            ->get(route('client.orders.delivery-attachments.download', [$order, $attachment]))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('client.orders.show', $order))
            ->assertOk()
            ->assertSee('Le travail demandé est terminé.')
            ->assertSee('livrable.pdf')
            ->assertSee('Confirmer la réception');

        $this->actingAs($client)
            ->post(route('client.orders.accept-delivery', $order))
            ->assertRedirect(route('client.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'status' => OrderStatus::COMPLETED->value,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::DELIVERY_SUBMITTED->value,
            'to_status' => OrderStatus::COMPLETED->value,
            'reason' => 'Le client a confirmé la réception du livrable.',
        ]);
    }

    public function test_client_can_request_delivery_corrections_and_order_returns_to_in_progress(): void
    {
        $client = $this->client();
        $order = $this->makeOrder($client, 'Maintenance serveur', OrderStatus::IN_PROGRESS);
        $professional = $order->professional->user;

        $this->actingAs($professional)
            ->post(route('professional.orders.submit-delivery', $order), [
                'summary' => 'La maintenance demandée est terminée. Merci de vérifier les éléments remis.',
            ])
            ->assertRedirect(route('professional.orders.show', $order));

        $this->actingAs($client)
            ->post(route('client.orders.request-delivery-changes', $order), [
                'reason' => 'La documentation de déploiement manque et doit être ajoutée au livrable.',
            ])
            ->assertRedirect(route('client.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'status' => OrderStatus::IN_PROGRESS->value,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::DELIVERY_SUBMITTED->value,
            'to_status' => OrderStatus::IN_PROGRESS->value,
            'reason' => 'Le client a demandé une correction du livrable.',
        ]);
    }

    public function test_professional_cannot_start_work_before_payment_succeeds(): void
    {
        $client = $this->client();
        $order = $this->makeOrder($client, 'Configuration réseau', OrderStatus::PENDING_PAYMENT);
        $professional = $order->professional->user;

        $this->actingAs($professional)
            ->post(route('professional.orders.start', $order))
            ->assertSessionHasErrors('order');

        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'status' => OrderStatus::PENDING_PAYMENT->value,
        ]);
    }

    public function test_professional_can_start_confirmed_order_only_after_successful_payment(): void
    {
        $client = $this->client();
        $order = $this->makeOrder($client, 'Installation informatique', OrderStatus::CONFIRMED);
        $professional = $order->professional->user;

        Payment::query()->forceCreate([
            'order_id' => $order->getKey(),
            'client_id' => $client->getKey(),
            'method' => PaymentMethod::MOBILE_MONEY,
            'provider' => PaymentProvider::FAKE,
            'status' => PaymentStatus::SUCCEEDED,
            'currency' => $order->currency,
            'amount' => $order->total,
            'paid_at' => now(),
        ]);

        $this->actingAs($professional)
            ->post(route('professional.orders.start', $order))
            ->assertRedirect(route('professional.orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'status' => OrderStatus::IN_PROGRESS->value,
        ]);
        $this->assertNotNull($order->fresh()->started_at);
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
