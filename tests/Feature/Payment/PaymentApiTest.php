<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Events\OrderPaid;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Payments\DTO\PaymentRequest;
use App\Payments\DTO\PaymentResult;
use App\Services\Order\OrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\FailingPaymentGateway;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_initiate_payment(): void
    {
        $response = $this->postJson('/api/v1/orders/1/payments', [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ]);

        $response->assertUnauthorized();
    }

    public function test_idempotency_key_is_required(): void
    {
        [$client, $order] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_professional_cannot_initiate_client_payment(): void
    {
        [$client, $order] = $this->orderScenario();
        [$professional] = $this->professional();

        $response = $this->actingAs($professional, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-professional-denied-001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertForbidden();
    }

    public function test_payment_amount_is_always_taken_from_server_order(): void
    {
        [$client, $order] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-server-amount-001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'amount' => '1.00',
                'currency' => 'EUR',
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertAccepted();

        $this->assertDatabaseHas('payment_intents', [
            'order_id' => $order->id,
            'amount' => 500,
            'currency' => 'USD',
        ]);
    }

    public function test_same_idempotency_key_returns_the_same_payment_intent(): void
    {
        config(['payment.fake.status' => PaymentStatus::PENDING->value]);

        [$client, $order] = $this->orderScenario();

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $first = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-retry-safe-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $first->assertAccepted();

        $second = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-retry-safe-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $second->assertAccepted();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('payment_intents', 1);
    }

    public function test_reusing_idempotency_key_with_different_operation_is_rejected(): void
    {
        config(['payment.fake.status' => PaymentStatus::PENDING->value]);

        [$client, $order] = $this->orderScenario();

        $first = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-fingerprint-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $first->assertAccepted();

        $second = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-fingerprint-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::CARD->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $second->assertConflict();
        $this->assertDatabaseCount('payment_intents', 1);
    }

    public function test_reusing_idempotency_key_on_another_order_does_not_mutate_the_second_order_payment(): void
    {
        config(['payment.fake.status' => PaymentStatus::PENDING->value]);

        [$client, $firstOrder] = $this->orderScenario();
        [, $secondOrder] = $this->orderScenarioForClient($client);

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $first = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-cross-order-0001')
            ->postJson('/api/v1/orders/'.$firstOrder->id.'/payments', $payload);

        $first->assertAccepted();

        $second = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-cross-order-0001')
            ->postJson('/api/v1/orders/'.$secondOrder->id.'/payments', $payload);

        $second->assertConflict();

        $this->assertDatabaseCount('payment_intents', 1);
        $this->assertDatabaseMissing('payments', ['order_id' => $secondOrder->id]);
    }

    public function test_second_different_payment_attempt_for_same_order_is_rejected_while_first_is_active(): void
    {
        config(['payment.fake.status' => PaymentStatus::PENDING->value]);

        [$client, $order] = $this->orderScenario();

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $first = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-active-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $first->assertAccepted();

        $second = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-active-0002')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $second->assertConflict();
        $this->assertDatabaseCount('payment_intents', 1);
    }

    public function test_failed_provider_result_is_persisted_without_confirming_order(): void
    {
        config(['payment.fake.status' => PaymentStatus::FAILED->value]);

        [$client, $order] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-failed-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('payment_intents', [
            'order_id' => $order->id,
            'status' => PaymentStatus::FAILED->value,
            'failure_code' => 'FAKE_PAYMENT_FAILED',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PENDING_PAYMENT->value,
        ]);
    }

    public function test_provider_amount_mismatch_is_persisted_as_failed_without_confirming_order(): void
    {
        $this->app->bind(PaymentGateway::class, static fn () => new class implements PaymentGateway
        {
            public function initiate(PaymentRequest $request): PaymentResult
            {
                return new PaymentResult(
                    status: PaymentStatus::SUCCEEDED,
                    amount: '1.00',
                    currency: $request->currency,
                    providerReference: 'FAKE-MISMATCHED-AMOUNT',
                    metadata: ['gateway' => 'mismatch-test'],
                );
            }
        });

        [$client, $order] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-provider-mismatch-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.status', PaymentStatus::FAILED->value);

        $paymentId = Payment::query()
            ->where('order_id', $order->id)
            ->value('id');

        $this->assertDatabaseHas('payment_transactions', [
            'payment_id' => $paymentId,
            'status' => 'failed',
            'failure_code' => 'PROVIDER_AMOUNT_MISMATCH',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => PaymentStatus::FAILED->value,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'order_id' => $order->id,
            'status' => PaymentStatus::FAILED->value,
            'failure_code' => 'PROVIDER_AMOUNT_MISMATCH',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PENDING_PAYMENT->value,
        ]);
    }

    public function test_provider_connection_failure_leaves_intent_retryable(): void
    {
        $this->app->bind(PaymentGateway::class, FailingPaymentGateway::class);

        [$client, $order] = $this->orderScenario();

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $first = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-network-failure-001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $first->assertStatus(503);

        $this->assertDatabaseHas('payment_intents', [
            'order_id' => $order->id,
            'idempotency_key' => 'payment-network-failure-001',
            'status' => PaymentStatus::INITIATED->value,
        ]);
        $this->assertDatabaseCount('payment_intents', 1);

        $second = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-network-failure-001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $second->assertStatus(503);
        $this->assertDatabaseCount('payment_intents', 1);
    }

    public function test_successful_payment_dispatches_order_paid_event(): void
    {
        config(['payment.fake.status' => PaymentStatus::SUCCEEDED->value]);
        Event::fake([OrderPaid::class]);

        [$client, $order] = $this->orderScenario();

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'payment-success-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'succeeded');
        $response->assertJsonPath('data.amount', '500.00');
        $response->assertJsonPath('data.currency', 'USD');

        $this->assertDatabaseHas('payment_intents', [
            'order_id' => $order->id,
            'status' => PaymentStatus::SUCCEEDED->value,
            'provider_reference' => 'FAKE-payment-success-0001',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CONFIRMED->value,
        ]);

        Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event): bool => $event->order->is($order));
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function orderScenario(): array
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $address = Address::factory()->default()->create([
            'user_id' => $client->id,
        ]);

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'address_id' => $address->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::QUOTED,
            'requested_at' => now(),
        ]);

        $quotation = Quotation::query()->forceCreate([
            'service_request_id' => $request->id,
            'status' => 'sent',
        ]);

        $offer = $quotation->offers()->forceCreate([
            'created_by' => $professional->id,
            'actor_type' => 'professional',
            'version' => 1,
            'amount' => 500,
            'currency' => 'USD',
            'description' => 'Service professionnel.',
            'duration_value' => 10,
            'duration_unit' => 'days',
            'conditions' => 'Conditions.',
            'valid_until' => now()->addDays(5),
        ]);

        $quotation->forceFill([
            'status' => 'accepted',
            'current_offer_id' => $offer->id,
            'accepted_offer_id' => $offer->id,
            'accepted_at' => now(),
        ])->save();

        $request->forceFill([
            'status' => ServiceRequestStatus::ACCEPTED,
        ])->save();

        $order = app(OrderService::class)->createFromAcceptedQuotation(
            $quotation->fresh(),
            $client,
        );

        return [$client, $order];
    }

    private function orderScenarioForClient(User $client): array
    {
        [$professional, $profile] = $this->professional();
        $service = $this->publishedService($profile);
        $address = Address::factory()->default()->create(['user_id' => $client->id]);

        $request = ServiceRequest::factory()->create([
            'client_id' => $client->id,
            'address_id' => $address->id,
            'professional_id' => $profile->id,
            'service_id' => $service->id,
            'status' => ServiceRequestStatus::ACCEPTED,
            'requested_at' => now(),
        ]);

        $quotation = Quotation::query()->forceCreate([
            'service_request_id' => $request->id,
            'status' => 'accepted',
        ]);

        $offer = $quotation->offers()->forceCreate([
            'created_by' => $professional->id,
            'actor_type' => 'professional',
            'version' => 1,
            'amount' => 500,
            'currency' => 'USD',
            'description' => 'Second service professionnel.',
            'duration_value' => 10,
            'duration_unit' => 'days',
            'conditions' => 'Conditions.',
            'valid_until' => now()->addDays(5),
        ]);

        $quotation->forceFill([
            'current_offer_id' => $offer->id,
            'accepted_offer_id' => $offer->id,
            'accepted_at' => now(),
        ])->save();

        return [$client, app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client)];
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    /**
     * @return array{0: User, 1: ProfessionalProfile}
     */
    private function professional(): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

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
