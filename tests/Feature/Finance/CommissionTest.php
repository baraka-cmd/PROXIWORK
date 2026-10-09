<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Order;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Order\OrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        config([
            'payment.fake.status' => PaymentStatus::SUCCEEDED->value,
            'commission.rate' => '10.00',
        ]);
    }

    public function test_successful_payment_creates_one_commission_and_posts_only_net_to_pending_wallet(): void
    {
        [$client, $order, $professional] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'commission-success-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('commissions', [
            'order_id' => $order->id,
            'payment_id' => $response->json('data.id'),
            'professional_id' => $professional->id,
            'gross_amount' => '500.00',
            'commission_rate' => '10.00',
            'commission_amount' => '50.00',
            'net_amount' => '450.00',
            'currency' => 'USD',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('wallets', [
            'professional_id' => $professional->id,
            'currency' => 'USD',
            'pending_balance' => '450.00',
            'available_balance' => '0.00',
        ]);

        $this->assertDatabaseCount('commissions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_repeated_successful_payment_request_does_not_create_second_commission(): void
    {
        [$client, $order] = $this->orderScenario();

        $payload = [
            'payment_method' => PaymentMethod::MOBILE_MONEY->value,
            'payment_provider' => PaymentProvider::FAKE->value,
        ];

        $first = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'commission-idempotency-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $second = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'commission-idempotency-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', $payload);

        $first->assertOk();
        $second->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('commissions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_commission_calculation_preserves_financial_invariant(): void
    {
        [$client, $order] = $this->orderScenario();

        $response = $this->actingAs($client, 'sanctum')
            ->withHeader('Idempotency-Key', 'commission-invariant-0001')
            ->postJson('/api/v1/orders/'.$order->id.'/payments', [
                'payment_method' => PaymentMethod::MOBILE_MONEY->value,
                'payment_provider' => PaymentProvider::FAKE->value,
            ]);

        $response->assertOk();

        $commission = Commission::query()->firstOrFail();

        $this->assertSame(
            '500.00',
            number_format(
                (float) $commission->commission_amount + (float) $commission->net_amount,
                2,
                '.',
                ''
            )
        );
        $this->assertSame(OrderStatus::CONFIRMED, Order::query()->findOrFail($order->id)->status);
    }

    /**
     * @return array{0: User, 1: Order, 2: ProfessionalProfile}
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

        return [
            $client,
            app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client),
            $profile,
        ];
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
