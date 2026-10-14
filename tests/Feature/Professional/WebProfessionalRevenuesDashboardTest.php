<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\QuotationOfferActor;
use App\Enums\QuotationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Address;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProfessionalProfile;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Order\OrderService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProfessionalRevenuesDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_empty_revenue_dashboard_does_not_invent_financial_amounts(): void
    {
        $professional = $this->professional();

        $this->actingAs($professional)
            ->get(route('professional.revenues.index'))
            ->assertOk()
            ->assertSee('Mes revenus')
            ->assertSee('Aucune donnée financière pour le moment')
            ->assertDontSee('450,00 USD');
    }

    public function test_dashboard_uses_recorded_commissions_and_separates_completed_orders_and_pending_wallet_balance(): void
    {
        [$professional, $order, $service] = $this->financialScenario();
        $order->forceFill([
            'status' => OrderStatus::COMPLETED,
            'completed_at' => now(),
        ])->save();

        $payment = Payment::query()->forceCreate([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'method' => PaymentMethod::MOBILE_MONEY,
            'provider' => PaymentProvider::FAKE,
            'status' => PaymentStatus::SUCCEEDED,
            'currency' => 'USD',
            'amount' => '500.00',
            'paid_at' => now(),
        ]);

        Commission::query()->forceCreate([
            'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(),
            'professional_id' => $order->professional_id,
            'gross_amount' => '500.00',
            'commission_rate' => '10.00',
            'commission_amount' => '50.00',
            'net_amount' => '450.00',
            'currency' => 'USD',
            'calculation_type' => 'percentage',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        Wallet::query()->forceCreate([
            'professional_id' => $order->professional_id,
            'currency' => 'USD',
            'available_balance' => '0.00',
            'pending_balance' => '450.00',
            'locked_balance' => '0.00',
            'status' => 'active',
        ]);

        $this->actingAs($professional)
            ->get(route('professional.revenues.index', ['period' => '30d', 'service_id' => $service->getKey()]))
            ->assertOk()
            ->assertSee('450,00 USD')
            ->assertSee('50,00 USD')
            ->assertSee('500,00 USD')
            ->assertSee('Fonds en attente de libération')
            ->assertSee('Historique des commissions')
            ->assertSee('COM-');
    }

    public function test_professional_cannot_view_another_professionals_commission_details(): void
    {
        [$owner, $order] = $this->financialScenario();
        $payment = Payment::query()->forceCreate([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'method' => PaymentMethod::MOBILE_MONEY,
            'provider' => PaymentProvider::FAKE,
            'status' => PaymentStatus::SUCCEEDED,
            'currency' => 'USD',
            'amount' => '500.00',
            'paid_at' => now(),
        ]);
        $commission = Commission::query()->forceCreate([
            'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(),
            'professional_id' => $order->professional_id,
            'gross_amount' => '500.00',
            'commission_rate' => '10.00',
            'commission_amount' => '50.00',
            'net_amount' => '450.00',
            'currency' => 'USD',
            'calculation_type' => 'percentage',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $otherProfessional = $this->professional();

        $this->assertNotSame($owner->getKey(), $otherProfessional->getKey());

        $this->actingAs($otherProfessional)
            ->get(route('professional.revenues.show', $commission->getKey()))
            ->assertNotFound();
    }

    public function test_csv_export_is_private_and_uses_the_professional_revenue_endpoint(): void
    {
        [$professional, $order] = $this->financialScenario();

        $payment = Payment::query()->forceCreate([
            'order_id' => $order->getKey(),
            'client_id' => $order->client_id,
            'method' => PaymentMethod::MOBILE_MONEY,
            'provider' => PaymentProvider::FAKE,
            'status' => PaymentStatus::SUCCEEDED,
            'currency' => 'USD',
            'amount' => '500.00',
            'paid_at' => now(),
        ]);

        Commission::query()->forceCreate([
            'order_id' => $order->getKey(),
            'payment_id' => $payment->getKey(),
            'professional_id' => $order->professional_id,
            'gross_amount' => '500.00',
            'commission_rate' => '10.00',
            'commission_amount' => '50.00',
            'net_amount' => '450.00',
            'currency' => 'USD',
            'calculation_type' => 'percentage',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $this->actingAs($professional)
            ->get(route('professional.revenues.export', ['period' => '30d']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('cache-control', 'private, no-store, max-age=0')
            ->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_custom_period_requires_both_dates_and_rejects_reversed_ranges(): void
    {
        $professional = $this->professional();

        $this->actingAs($professional)
            ->get(route('professional.revenues.index', ['from' => '2026-10-10']))
            ->assertSessionHasErrors(['to']);

        $this->actingAs($professional)
            ->get(route('professional.revenues.index', ['from' => '2026-10-12', 'to' => '2026-10-01']))
            ->assertSessionHasErrors(['to']);
    }

    /** @return array{0: User, 1: Order, 2: Service} */
    private function financialScenario(): array
    {
        $professional = $this->professional();
        $profile = $professional->professionalProfile()->firstOrFail();
        $client = User::factory()->create();
        $client->assignRole('client');

        $service = Service::factory()->create([
            'professional_profile_id' => $profile->getKey(),
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        $address = Address::factory()->create(['user_id' => $client->getKey()]);
        $request = ServiceRequest::factory()->create([
            'client_id' => $client->getKey(),
            'professional_id' => $profile->getKey(),
            'service_id' => $service->getKey(),
            'address_id' => $address->getKey(),
            'status' => ServiceRequestStatus::ACCEPTED,
        ]);
        $quotation = Quotation::factory()->create([
            'service_request_id' => $request->getKey(),
            'status' => QuotationStatus::ACCEPTED,
            'accepted_at' => now(),
        ]);
        $offer = $quotation->offers()->forceCreate([
            'created_by' => $professional->getKey(),
            'actor_type' => QuotationOfferActor::PROFESSIONAL->value,
            'version' => 1,
            'amount' => '500.00',
            'currency' => 'USD',
            'description' => 'Prestation test.',
            'duration_value' => 2,
            'duration_unit' => 'days',
            'conditions' => 'Conditions test.',
            'valid_until' => now()->addDays(3),
        ]);
        $quotation->forceFill([
            'current_offer_id' => $offer->getKey(),
            'accepted_offer_id' => $offer->getKey(),
        ])->save();

        $order = app(OrderService::class)->createFromAcceptedQuotation($quotation->fresh(), $client);

        return [$professional, $order, $service];
    }

    private function professional(): User
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->getKey()]);

        return $user;
    }
}
