<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebProfessionalWalletDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_opening_wallet_page_does_not_create_a_financial_account(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('professional.wallet'))
            ->assertOk()
            ->assertViewIs('professional.wallet.index')
            ->assertSee('Votre portefeuille est en attente d’activité')
            ->assertSee('Cette page de consultation ne crée et ne modifie aucun solde');

        $this->assertDatabaseCount('wallets', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_wallet_history_filters_are_applied_to_the_authenticated_professionals_wallet(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');

        $matching = $this->createTransaction($wallet, [
            'type' => WalletTransactionType::EARNING,
            'direction' => WalletTransactionDirection::CREDIT,
            'description' => 'WALLET_FILTER_MATCH',
            'created_at' => CarbonImmutable::parse('2026-10-10 12:00:00'),
        ]);

        $this->createTransaction($wallet, [
            'type' => WalletTransactionType::WITHDRAWAL,
            'direction' => WalletTransactionDirection::DEBIT,
            'description' => 'WALLET_FILTER_MUST_NOT_MATCH',
            'created_at' => CarbonImmutable::parse('2026-10-11 12:00:00'),
        ]);

        $this->actingAs($user)
            ->get(route('professional.wallet', [
                'currency' => 'USD',
                'type' => WalletTransactionType::EARNING->value,
                'direction' => WalletTransactionDirection::CREDIT->value,
                'transaction_id' => $matching->id,
                'date_from' => '2026-10-10',
                'date_to' => '2026-10-10',
            ]))
            ->assertOk()
            ->assertSee('WALLET_FILTER_MATCH')
            ->assertDontSee('WALLET_FILTER_MUST_NOT_MATCH');
    }

    public function test_wallet_history_never_exposes_another_professionals_transactions(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('professional');
        $ownerProfile = ProfessionalProfile::factory()->create(['user_id' => $owner->id]);
        $wallet = app(WalletService::class)->getOrCreate($ownerProfile, 'USD');

        $this->createTransaction($wallet, [
            'description' => 'PRIVATE_OWNER_LEDGER_ENTRY',
        ]);

        $attacker = User::factory()->create();
        $attacker->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $attacker->id]);

        $this->actingAs($attacker)
            ->get(route('professional.wallet', ['currency' => 'USD']))
            ->assertOk()
            ->assertSee('Votre portefeuille est en attente d’activité')
            ->assertDontSee('PRIVATE_OWNER_LEDGER_ENTRY');
    }

    public function test_non_active_wallet_displays_warning_and_hides_withdrawal_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($user)
            ->get(route('professional.wallet', ['currency' => 'USD']))
            ->assertOk()
            ->assertSee('Portefeuille suspended')
            ->assertDontSee('Demander un retrait');
    }

    public function test_wallet_page_rejects_a_currency_that_does_not_belong_to_the_professional(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        app(WalletService::class)->getOrCreate($profile, 'USD');

        $this->actingAs($user)
            ->from(route('professional.wallet'))
            ->get(route('professional.wallet', ['currency' => 'EUR']))
            ->assertRedirect(route('professional.wallet'))
            ->assertSessionHasErrors('currency');
    }

    public function test_wallet_history_rejects_custom_periods_longer_than_366_days(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        app(WalletService::class)->getOrCreate($profile, 'USD');

        $this->actingAs($user)
            ->from(route('professional.wallet'))
            ->get(route('professional.wallet', [
                'currency' => 'USD',
                'date_from' => '2025-01-01',
                'date_to' => '2026-10-10',
            ]))
            ->assertRedirect(route('professional.wallet'))
            ->assertSessionHasErrors('date_to');
    }

    private function createTransaction(Wallet $wallet, array $attributes = []): WalletTransaction
    {
        static $sequence = 0;
        $sequence++;

        return WalletTransaction::query()->forceCreate(array_merge([
            'wallet_id' => $wallet->getKey(),
            'type' => WalletTransactionType::EARNING,
            'direction' => WalletTransactionDirection::CREDIT,
            'currency' => $wallet->currency,
            'amount' => '12.34',
            'balance_before' => '0.00',
            'balance_after' => '12.34',
            'idempotency_key' => 'wallet-dashboard-test-'.$sequence,
            'description' => 'WALLET_TEST_TRANSACTION',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
}
