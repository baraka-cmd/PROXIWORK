<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalStatusHistory;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WithdrawalLedgerImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_wallet_ledger_entries_cannot_be_updated_or_deleted(): void
    {
        [$profile, $withdrawal] = $this->createWithdrawal();

        $transaction = WalletTransaction::query()
            ->where('reference_type', $withdrawal::class)
            ->where('reference_id', $withdrawal->getKey())
            ->firstOrFail();
        $originalDescription = $transaction->description;

        try {
            $transaction->forceFill(['description' => 'Tentative de modification'])->save();
            $this->fail('A wallet ledger entry must be append-only.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immuables', $exception->getMessage());
        }

        try {
            $transaction->delete();
            $this->fail('A wallet ledger entry must not be deletable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('supprimées', $exception->getMessage());
        }

        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $transaction->getKey(),
            'description' => $originalDescription,
        ]);
    }

    public function test_withdrawal_status_history_cannot_be_updated_or_deleted(): void
    {
        [, $withdrawal] = $this->createWithdrawal();

        $history = WithdrawalStatusHistory::query()
            ->where('withdrawal_id', $withdrawal->getKey())
            ->firstOrFail();
        $originalReason = $history->reason;

        try {
            $history->forceFill(['reason' => 'Tentative de réécriture'])->save();
            $this->fail('A withdrawal status history entry must be append-only.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immuable', $exception->getMessage());
        }

        try {
            $history->delete();
            $this->fail('A withdrawal status history entry must not be deletable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('supprimé', $exception->getMessage());
        }

        $this->assertDatabaseHas('withdrawal_status_histories', [
            'id' => $history->getKey(),
            'reason' => $originalReason,
        ]);
    }

    /**
     * @return array{0: ProfessionalProfile, 1: \App\Models\Withdrawal}
     */
    private function createWithdrawal(): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->getKey()]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();

        $withdrawal = app(WithdrawalService::class)->request(
            professional: $profile,
            amount: '25.00',
            currency: 'USD',
            provider: 'mobile_money',
            destination: '243970123456',
            idempotencyKey: 'ledger-immutability-test-0001-'.uniqid(),
        );

        return [$profile, $withdrawal];
    }
}
