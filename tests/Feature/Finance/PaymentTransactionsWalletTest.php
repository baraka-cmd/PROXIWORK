<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\PaymentConflictException;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use App\Services\Payment\PaymentWebhookService;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tests\TestCase;

class PaymentTransactionsWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        config(['payment.fake.status' => PaymentStatus::PENDING->value]);
    }

    public function test_payment_creates_one_payment_and_one_transaction_for_an_idempotent_request(): void
    {
        [$client, $order] = $this->orderScenario();

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $first = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-idempotency-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $second = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-idempotency-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $first->assertAccepted();
        $second->assertAccepted();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_different_idempotency_key_cannot_create_second_active_payment_for_same_order(): void
    {
        [$client, $order] = $this->orderScenario();

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-active-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload)
            ->assertAccepted();

        $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-active-0002')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload)
            ->assertConflict();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_successful_payment_confirms_order_once(): void
    {
        config(['payment.fake.status' => PaymentStatus::SUCCEEDED->value]);

        [$client, $order] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-success-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', PaymentStatus::SUCCEEDED->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CONFIRMED->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::SUCCEEDED->value,
        ]);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_client_amount_is_not_authoritative(): void
    {
        [$client, $order] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-server-amount-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'amount' => '1.00',
                'currency' => 'EUR',
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertAccepted();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => '500.00',
            'currency' => 'USD',
        ]);
    }

    public function test_duplicate_webhook_event_has_one_effect(): void
    {
        config(['payment.webhooks.secrets.fake' => 'test-webhook-secret']);

        [$client, $order] = $this->orderScenario();

        $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-webhook-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ])
            ->assertAccepted();

        $transaction = PaymentTransaction::query()->firstOrFail();
        $rawBody = 'event-1|'.$transaction->provider_transaction_id.'|500.00|USD|succeeded';
        $signature = hash_hmac('sha256', $rawBody, 'test-webhook-secret');

        $service = app(PaymentWebhookService::class);

        $first = $service->handle(
            provider: PaymentProvider::FAKE,
            signature: $signature,
            eventId: 'EVENT-1',
            transactionId: $transaction->provider_transaction_id,
            amount: '500.00',
            currency: 'USD',
            status: PaymentStatus::SUCCEEDED,
            failureCode: null,
            failureMessage: null,
            metadata: ['source' => 'test'],
            rawBody: $rawBody,
        );

        $second = $service->handle(
            provider: PaymentProvider::FAKE,
            signature: $signature,
            eventId: 'EVENT-1',
            transactionId: $transaction->provider_transaction_id,
            amount: '500.00',
            currency: 'USD',
            status: PaymentStatus::SUCCEEDED,
            failureCode: null,
            failureMessage: null,
            metadata: ['source' => 'test'],
            rawBody: $rawBody,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('order_status_histories', 2);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CONFIRMED->value,
        ]);
    }

    public function test_webhook_http_endpoint_rejects_invalid_signature(): void
    {
        config(['payment.webhooks.secrets.fake' => 'real-secret']);

        [$client, $order] = $this->orderScenario();

        $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-webhook-http-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ])
            ->assertAccepted();

        $transaction = PaymentTransaction::query()->firstOrFail();

        $this->postJson('/api/v1/payments/webhooks/fake', [
            'event_id' => 'HTTP-EVENT-INVALID',
            'transaction_id' => $transaction->provider_transaction_id,
            'amount' => '500.00',
            'currency' => 'USD',
            'status' => PaymentStatus::SUCCEEDED->value,
        ], [
            'X-Payment-Signature' => 'invalid-signature',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PENDING_PAYMENT->value,
        ]);
    }

    public function test_webhook_http_endpoint_confirms_payment_with_valid_signature(): void
    {
        config(['payment.webhooks.secrets.fake' => 'test-webhook-secret']);

        [$client, $order] = $this->orderScenario();

        $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-webhook-http-0002')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ])
            ->assertAccepted();

        $transaction = PaymentTransaction::query()->firstOrFail();
        $payload = [
            'event_id' => 'HTTP-EVENT-VALID',
            'transaction_id' => $transaction->provider_transaction_id,
            'amount' => '500.00',
            'currency' => 'USD',
            'status' => PaymentStatus::SUCCEEDED->value,
            'metadata' => ['source' => 'http-test'],
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, 'test-webhook-secret');

        $this->call(
            'POST',
            '/api/v1/payments/webhooks/fake',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYMENT_SIGNATURE' => $signature,
            ],
            content: $rawBody,
        )->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::SUCCEEDED->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CONFIRMED->value,
        ]);
        $this->assertDatabaseCount('commissions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        config(['payment.webhooks.secrets.fake' => 'real-secret']);

        [$client, $order] = $this->orderScenario();

        $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'finance-webhook-0002')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ])
            ->assertAccepted();

        $transaction = PaymentTransaction::query()->firstOrFail();

        $this->expectException(UnauthorizedHttpException::class);

        app(PaymentWebhookService::class)->handle(
            provider: PaymentProvider::FAKE,
            signature: 'invalid',
            eventId: 'EVENT-INVALID',
            transactionId: $transaction->provider_transaction_id,
            amount: '500.00',
            currency: 'USD',
            status: PaymentStatus::SUCCEEDED,
            failureCode: null,
            failureMessage: null,
            metadata: [],
            rawBody: 'tampered',
        );
    }

    public function test_wallet_credit_is_idempotent(): void
    {
        [, $professional] = $this->professional();
        $service = app(WalletService::class);

        $first = $service->creditPending(
            professional: $professional,
            amount: '450.00',
            currency: 'USD',
            type: WalletTransactionType::EARNING,
            idempotencyKey: 'wallet-credit-000001',
        );

        $second = $service->creditPending(
            professional: $professional,
            amount: '450.00',
            currency: 'USD',
            type: WalletTransactionType::EARNING,
            idempotencyKey: 'wallet-credit-000001',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallets', [
            'professional_id' => $professional->id,
            'pending_balance' => '450.00',
        ]);
    }

    public function test_withdrawal_locks_balance_and_second_withdrawal_cannot_spend_same_money(): void
    {
        [, $professional] = $this->professional();
        $walletService = app(WalletService::class);
        $withdrawalService = app(WithdrawalService::class);

        $walletService->creditPending(
            professional: $professional,
            amount: '1000.00',
            currency: 'USD',
            type: WalletTransactionType::EARNING,
            idempotencyKey: 'wallet-credit-000002',
        );
        $walletService->releasePending(
            professional: $professional,
            amount: '1000.00',
            currency: 'USD',
            idempotencyKey: 'wallet-release-000001',
        );

        $withdrawalService->request(
            professional: $professional,
            amount: '800.00',
            currency: 'USD',
            provider: 'fake',
            destination: '0000000000',
            idempotencyKey: 'withdrawal-000001',
        );

        $this->expectException(PaymentConflictException::class);

        $withdrawalService->request(
            professional: $professional,
            amount: '300.00',
            currency: 'USD',
            provider: 'fake',
            destination: '0000000000',
            idempotencyKey: 'withdrawal-000002',
        );
    }

    public function test_withdrawal_retry_with_same_key_returns_same_request(): void
    {
        [, $professional] = $this->professional();
        $walletService = app(WalletService::class);
        $withdrawalService = app(WithdrawalService::class);

        $walletService->creditPending(
            professional: $professional,
            amount: '500.00',
            currency: 'USD',
            type: WalletTransactionType::EARNING,
            idempotencyKey: 'wallet-credit-000003',
        );
        $walletService->releasePending(
            professional: $professional,
            amount: '500.00',
            currency: 'USD',
            idempotencyKey: 'wallet-release-000002',
        );

        $first = $withdrawalService->request(
            professional: $professional,
            amount: '300.00',
            currency: 'USD',
            provider: 'fake',
            destination: '0000000000',
            idempotencyKey: 'withdrawal-retry-0001',
        );

        $second = $withdrawalService->request(
            professional: $professional,
            amount: '300.00',
            currency: 'USD',
            provider: 'fake',
            destination: '0000000000',
            idempotencyKey: 'withdrawal-retry-0001',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('withdrawals', 1);
    }

    public function test_failed_withdrawal_releases_locked_balance(): void
    {
        [, $professional] = $this->professional();
        $walletService = app(WalletService::class);
        $withdrawalService = app(WithdrawalService::class);

        $walletService->creditPending(
            professional: $professional,
            amount: '500.00',
            currency: 'USD',
            type: WalletTransactionType::EARNING,
            idempotencyKey: 'wallet-credit-000004',
        );
        $walletService->releasePending(
            professional: $professional,
            amount: '500.00',
            currency: 'USD',
            idempotencyKey: 'wallet-release-000003',
        );

        $withdrawal = $withdrawalService->request(
            professional: $professional,
            amount: '300.00',
            currency: 'USD',
            provider: 'fake',
            destination: '0000000000',
            idempotencyKey: 'withdrawal-failure-0001',
        );

        $withdrawalService->markFailed($withdrawal, 'FAKE_FAILURE', 'Échec simulé.');

        $this->assertDatabaseHas('wallets', [
            'professional_id' => $professional->id,
            'available_balance' => '500.00',
            'locked_balance' => '0.00',
        ]);
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function orderScenario(): array
    {
        [$professional, $profile] = $this->professional();
        $client = $this->client();
        $service = $this->publishedService($profile);
        $address = Address::factory()->default()->create(['user_id' => $client->id]);

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

        $request->forceFill(['status' => ServiceRequestStatus::ACCEPTED])->save();

        return [$client, app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client)];
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }

    /** @return array{0: User, 1: ProfessionalProfile} */
    private function professional(): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        return [$user, ProfessionalProfile::factory()->create(['user_id' => $user->id])];
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
