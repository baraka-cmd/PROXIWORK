<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\PaymentConflictException;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
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
        [, $withdrawal] = $this->createWithdrawal();

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

    public function test_mismatched_existing_idempotency_entry_rolls_back_the_financial_transition(): void
    {
        [, $withdrawal] = $this->createWithdrawal();
        $service = app(WithdrawalService::class);
        $service->markProcessing($withdrawal);

        $wallet = $withdrawal->wallet()->firstOrFail();
        $wallet->refresh();
        $conflictingKey = 'withdrawal:'.$withdrawal->getKey().':failed:locked';

        WalletTransaction::query()->forceCreate([
            'wallet_id' => $wallet->getKey(),
            'type' => WalletTransactionType::WITHDRAWAL,
            'direction' => WalletTransactionDirection::DEBIT,
            'currency' => $withdrawal->currency,
            'amount' => $withdrawal->amount,
            'balance_before' => $wallet->locked_balance,
            'balance_after' => '0.00',
            'balance_bucket' => 'available',
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->getKey(),
            'idempotency_key' => $conflictingKey,
            'description' => 'Écriture contradictoire injectée par le test.',
        ]);

        try {
            $service->markFailed(
                $withdrawal,
                'PROVIDER_CONFIRMED_FAILURE',
                'Le résultat négatif a été confirmé par la vérification manuelle.',
            );
            $this->fail('A conflicting idempotency entry must abort the transition.');
        } catch (PaymentConflictException $exception) {
            $this->assertStringContainsString('données différentes', $exception->getMessage());
        }

        $this->assertSame(WithdrawalStatus::PROCESSING, $withdrawal->fresh()->status);
        $this->assertSame('75.00', $wallet->fresh()->available_balance);
        $this->assertSame('25.00', $wallet->fresh()->locked_balance);
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
     * @return array{0: ProfessionalProfile, 1: Withdrawal}
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
