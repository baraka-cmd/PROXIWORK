<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Wallet;
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

    public function test_finance_admin_can_open_the_five_read_only_supervision_areas(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.finance.overview'))
            ->assertOk()
            ->assertSee('Vue générale des finances');

        $this->get(route('admin.payments.index'))->assertOk();
        $this->get(route('admin.finance.transactions'))->assertOk();
        $this->get(route('admin.finance.commissions'))->assertOk();
        $this->get(route('admin.finance.wallets'))->assertOk();
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

    public function test_wallet_supervision_is_read_only_and_does_not_expose_mutation_forms(): void
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

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.finance.wallets.show', $wallet))
            ->assertOk()
            ->assertSee('75,00 USD')
            ->assertSee('25,00 USD')
            ->assertDontSee('name="available_balance"')
            ->assertDontSee('name="pending_balance"')
            ->assertDontSee('name="locked_balance"');
    }
}
