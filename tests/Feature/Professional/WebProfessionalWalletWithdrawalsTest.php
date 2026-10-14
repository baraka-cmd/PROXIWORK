<?php

declare(strict_types=1);

namespace Tests\Feature\Professional;

use App\Enums\WithdrawalStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebProfessionalWalletWithdrawalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_professional_can_view_wallet(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        app(WalletService::class)->getOrCreate($profile, 'USD');

        $this->actingAs($user)
            ->get(route('professional.wallet'))
            ->assertOk()
            ->assertViewIs('professional.wallet.index')
            ->assertSee('Disponible');
    }

    public function test_client_cannot_access_wallet(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->get(route('professional.wallet'))
            ->assertForbidden();
    }

    public function test_professional_can_request_withdrawal_and_amount_is_locked(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();

        $response = $this->actingAs($user)->post(route('professional.withdrawals.store'), [
            'amount' => '25.50',
            'currency' => 'USD',
            'provider' => 'mobile_money',
            'destination' => '0000000000',
            'idempotency_key' => 'web-'.Str::random(32),
            'confirm_request' => '1',
        ]);

        $withdrawal = Withdrawal::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('professional.withdrawals.show', $withdrawal));

        $wallet->refresh();

        $this->assertSame('74.50', $wallet->available_balance);
        $this->assertSame('25.50', $wallet->locked_balance);
        $this->assertSame(WithdrawalStatus::REQUESTED, $withdrawal->status);
    }

    public function test_professional_cannot_view_another_professional_withdrawal(): void
    {
        $attacker = User::factory()->create();
        $attacker->assignRole('professional');
        $owner = User::factory()->create();
        $owner->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $owner->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');

        $withdrawal = Withdrawal::query()->forceCreate([
            'wallet_id' => $wallet->id,
            'professional_id' => $profile->id,
            'provider' => 'mobile_money',
            'destination' => '0000000000',
            'status' => WithdrawalStatus::REQUESTED,
            'currency' => 'USD',
            'amount' => '10.00',
            'idempotency_key' => 'test-'.Str::random(32),
            'requested_at' => now(),
        ]);

        $this->actingAs($attacker)
            ->get(route('professional.withdrawals.show', $withdrawal))
            ->assertForbidden();
    }

    public function test_failed_withdrawal_returns_locked_amount(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '90.00'])->save();

        $withdrawal = app(WithdrawalService::class)->request(
            $profile,
            '20.00',
            'USD',
            'mobile_money',
            '0000000000',
            'test-'.Str::random(32),
        );

        app(WithdrawalService::class)->markProcessing($withdrawal);
        app(WithdrawalService::class)->markFailed($withdrawal, 'provider_failed', 'Test failure');

        $wallet->refresh();

        $this->assertSame('90.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->locked_balance);
    }

    public function test_duplicate_idempotency_key_does_not_lock_balance_twice(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();
        $key = 'web-'.Str::random(32);
        $service = app(WithdrawalService::class);

        $first = $service->request($profile, '30.00', 'USD', 'mobile_money', '0000000000', $key);
        $second = $service->request($profile, '30.00', 'USD', 'mobile_money', '0000000000', $key);

        $wallet->refresh();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('70.00', $wallet->available_balance);
        $this->assertSame('30.00', $wallet->locked_balance);
        $this->assertSame(1, Withdrawal::query()->where('idempotency_key', $key)->count());
    }

    public function test_opening_withdrawal_form_does_not_create_a_wallet(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('professional.withdrawals.create', ['currency' => 'USD']))
            ->assertRedirect(route('professional.withdrawals'));

        $this->assertDatabaseCount('wallets', 0);
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_withdrawal_request_encrypts_destination_and_records_both_reservation_buckets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();
        $destination = '243970123456';

        $response = $this->actingAs($user)->post(route('professional.withdrawals.store'), [
            'amount' => '25.50',
            'currency' => 'USD',
            'provider' => 'mobile_money',
            'destination' => $destination,
            'idempotency_key' => 'web-encrypted-destination-0001',
            'confirm_request' => '1',
        ]);

        $withdrawal = Withdrawal::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('professional.withdrawals.show', $withdrawal));

        $withdrawal->refresh();
        $this->assertSame($destination, $withdrawal->destination_ciphertext);
        $this->assertNotSame($destination, $withdrawal->getRawOriginal('destination_ciphertext'));
        $this->assertStringNotContainsString($destination, (string) $withdrawal->getRawOriginal('destination'));
        $this->assertSame('74.50', $wallet->fresh()->available_balance);
        $this->assertSame('25.50', $wallet->fresh()->locked_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'balance_bucket' => 'available',
            'direction' => 'debit',
            'idempotency_key' => 'withdrawal:'.$withdrawal->id.':reserve:available',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'balance_bucket' => 'locked',
            'direction' => 'credit',
            'idempotency_key' => 'withdrawal:'.$withdrawal->id.':reserve:locked',
        ]);
        $this->assertDatabaseCount('withdrawal_status_histories', 1);

        $this->actingAs($user)
            ->get(route('professional.withdrawals.show', $withdrawal))
            ->assertOk()
            ->assertSee('Destination masquée')
            ->assertDontSee($destination);
    }

    public function test_inactive_wallet_cannot_reserve_funds(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00', 'status' => 'suspended'])->save();

        try {
            app(WithdrawalService::class)->request($profile, '10.00', 'USD', 'mobile_money', '243970123456', 'inactive-wallet-request-001');
            $this->fail('An inactive wallet must not reserve withdrawal funds.');
        } catch (\App\Exceptions\PaymentConflictException $exception) {
            $this->assertStringContainsString('n’est pas autorisé', $exception->getMessage());
        }

        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertSame('100.00', $wallet->fresh()->available_balance);
        $this->assertSame('0.00', $wallet->fresh()->locked_balance);
    }

    public function test_successful_withdrawal_requires_processing_and_is_idempotent(): void
    {
        [, $user, $profile, $wallet] = $this->professionalWithAvailableWallet('100.00');
        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($profile, '30.00', 'USD', 'mobile_money', '243970123456', 'withdraw-success-test-0001');

        try {
            $service->markSucceeded($withdrawal, 'MOMO-REF-0001');
            $this->fail('A requested withdrawal must not jump directly to succeeded.');
        } catch (\App\Exceptions\PaymentConflictException $exception) {
            $this->assertStringContainsString('doit être en traitement', $exception->getMessage());
        }

        $this->assertSame('30.00', $wallet->fresh()->locked_balance);

        $service->markProcessing($withdrawal, $user->id, 'Dossier contrôlé avant transmission manuelle.');
        $succeeded = $service->markSucceeded($withdrawal, 'MOMO-REF-0001', $user->id, 'Preuve externe contrôlée et rapprochée.');
        $retry = $service->markSucceeded($withdrawal, 'MOMO-REF-0001', $user->id, 'Nouvel appel idempotent.');

        $this->assertSame(WithdrawalStatus::SUCCEEDED, $succeeded->status);
        $this->assertSame($succeeded->id, $retry->id);
        $this->assertSame('70.00', $wallet->fresh()->available_balance);
        $this->assertSame('0.00', $wallet->fresh()->locked_balance);
        $this->assertDatabaseCount('wallet_transactions', 3);
        $this->assertDatabaseCount('withdrawal_status_histories', 3);
        $this->assertDatabaseHas('wallet_transactions', [
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'balance_bucket' => 'locked',
            'direction' => 'debit',
            'idempotency_key' => 'withdrawal:'.$withdrawal->id.':succeeded:locked',
        ]);
    }

    public function test_confirmed_failure_releases_reservation_once_and_records_all_bucket_movements(): void
    {
        [, , $profile, $wallet] = $this->professionalWithAvailableWallet('100.00');
        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($profile, '40.00', 'USD', 'bank_transfer', 'BANK-DEST-TEST', 'withdraw-failure-test-0001');
        $service->markProcessing($withdrawal, null, 'Traitement manuel commencé pour le test.');
        $service->markFailed($withdrawal, 'BANK_REJECTED', 'Le transfert a été refusé par la banque.', null, 'Échec vérifié.');
        $service->markFailed($withdrawal, 'BANK_REJECTED', 'Le transfert a été refusé par la banque.', null, 'Nouvel appel idempotent.');

        $this->assertSame('100.00', $wallet->fresh()->available_balance);
        $this->assertSame('0.00', $wallet->fresh()->locked_balance);
        $this->assertDatabaseCount('wallet_transactions', 4);
        $this->assertDatabaseCount('withdrawal_status_histories', 3);
        $this->assertDatabaseHas('wallet_transactions', [
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'balance_bucket' => 'available',
            'direction' => 'credit',
            'idempotency_key' => 'withdrawal:'.$withdrawal->id.':failed:available',
        ]);
    }

    public function test_cancellation_is_only_allowed_before_processing_and_releases_once(): void
    {
        [$user, , $profile, $wallet] = $this->professionalWithAvailableWallet('100.00');
        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($profile, '15.00', 'USD', 'mobile_money', '243970123456', 'withdraw-cancel-test-0001');

        $service->cancel($withdrawal, $user->id);
        $service->cancel($withdrawal, $user->id);

        $this->assertSame(WithdrawalStatus::CANCELLED, $withdrawal->fresh()->status);
        $this->assertSame('100.00', $wallet->fresh()->available_balance);
        $this->assertSame('0.00', $wallet->fresh()->locked_balance);
        $this->assertDatabaseCount('wallet_transactions', 4);
        $this->assertDatabaseCount('withdrawal_status_histories', 2);
    }

    public function test_uncertain_result_keeps_funds_locked_until_a_reconciled_outcome(): void
    {
        [$user, , $profile, $wallet] = $this->professionalWithAvailableWallet('100.00');
        $service = app(WithdrawalService::class);
        $withdrawal = $service->request($profile, '20.00', 'USD', 'mobile_money', '243970123456', 'withdraw-review-test-0001');
        $service->markProcessing($withdrawal, $user->id, 'Transmission manuelle démarrée.');
        $service->markReviewRequired($withdrawal, 'Le résultat du transfert est ambigu après une interruption réseau.', $user->id);

        $this->assertSame(WithdrawalStatus::REVIEW_REQUIRED, $withdrawal->fresh()->status);
        $this->assertSame('80.00', $wallet->fresh()->available_balance);
        $this->assertSame('20.00', $wallet->fresh()->locked_balance);
        $this->assertDatabaseCount('wallet_transactions', 2);

        $service->markFailed($withdrawal, 'PROVIDER_CONFIRMED_FAILURE', 'Le prestataire confirme que le transfert n’a pas été exécuté.', $user->id, 'Rapprochement terminé.');

        $this->assertSame('100.00', $wallet->fresh()->available_balance);
        $this->assertSame('0.00', $wallet->fresh()->locked_balance);
        $this->assertDatabaseCount('wallet_transactions', 4);
    }

    private function professionalWithAvailableWallet(string $available): array
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => $available])->save();

        return [$user, $user, $profile, $wallet];
    }

}
