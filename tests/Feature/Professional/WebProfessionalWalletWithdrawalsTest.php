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
}
