<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Admin\Finance\AdminFinanceQueryService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceSupervisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_financial_read_permissions_are_separated_from_general_payment_access(): void
    {
        $support = User::factory()->create();
        $support->assignRole('support');

        $this->actingAs($support)
            ->get(route('admin.payments.index'))
            ->assertOk();

        $this->get(route('admin.finance.overview'))->assertForbidden();
        $this->get(route('admin.finance.transactions'))->assertForbidden();
        $this->get(route('admin.finance.commissions'))->assertForbidden();
        $this->get(route('admin.finance.wallets'))->assertForbidden();
    }

    public function test_overview_keeps_wallet_balances_separated_by_currency(): void
    {
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->getKey(),
        ]);

        Wallet::query()->forceCreate([
            'professional_id' => $professional->getKey(),
            'currency' => 'USD',
            'available_balance' => '100.00',
            'pending_balance' => '20.00',
            'locked_balance' => '5.00',
            'status' => 'active',
        ]);

        Wallet::query()->forceCreate([
            'professional_id' => $professional->getKey(),
            'currency' => 'CDF',
            'available_balance' => '250000.00',
            'pending_balance' => '10000.00',
            'locked_balance' => '0.00',
            'status' => 'active',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.finance.overview'))
            ->assertOk()
            ->assertSee('100,00 USD')
            ->assertSee('250 000,00 CDF')
            ->assertSee('Soldes actuels');
    }

    public function test_wallet_query_service_is_read_only_and_returns_professional_context(): void
    {
        $professionalUser = User::factory()->create();
        $professionalUser->assignRole('professional');
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->getKey(),
        ]);

        $wallet = Wallet::query()->forceCreate([
            'professional_id' => $professional->getKey(),
            'currency' => 'USD',
            'available_balance' => '75.00',
            'pending_balance' => '25.00',
            'locked_balance' => '10.00',
            'status' => 'active',
        ]);

        $service = app(AdminFinanceQueryService::class);
        $page = $service->wallets(['search' => $professionalUser->name]);
        $detail = $service->wallet($wallet->fresh());

        $this->assertSame($wallet->getKey(), $page->firstOrFail()->getKey());
        $this->assertSame($professionalUser->getKey(), $detail->professional->user->getKey());
        $this->assertSame('75.00', $detail->available_balance);
        $this->assertSame('25.00', $detail->pending_balance);
        $this->assertSame('10.00', $detail->locked_balance);
        $this->assertCount(0, $detail->recentTransactions);

        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->getKey(),
            'available_balance' => '75.00',
            'pending_balance' => '25.00',
            'locked_balance' => '10.00',
        ]);
    }
}
