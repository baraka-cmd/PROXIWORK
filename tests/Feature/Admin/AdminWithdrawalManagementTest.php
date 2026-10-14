<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\WithdrawalStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Wallet\WithdrawalService;
use App\Services\Wallet\WalletService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWithdrawalManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_only_financially_authorized_admin_can_open_withdrawal_queue(): void
    {
        [$professional] = $this->createWithdrawal();

        $this->actingAs($professional)
            ->get(route('admin.withdrawals.index'))
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertSee('Retraits professionnels');
    }

    public function test_admin_can_start_manual_processing_and_confirm_success_with_audit_trail(): void
    {
        [, $withdrawal, $wallet] = $this->createWithdrawal();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.processing', $withdrawal), [
                'reason' => 'Les informations du dossier ont été contrôlées.',
            ])
            ->assertRedirect(route('admin.withdrawals.show', $withdrawal));

        $this->assertSame(WithdrawalStatus::PROCESSING, $withdrawal->fresh()->status);
        $this->assertSame('30.00', $wallet->fresh()->locked_balance);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.succeeded', $withdrawal), [
                'provider_transaction_id' => 'MANUAL-PROOF-2026-0001',
                'reason' => 'Preuve de versement vérifiée et rapprochée par la finance.',
            ])
            ->assertRedirect(route('admin.withdrawals.show', $withdrawal));

        $this->assertSame(WithdrawalStatus::SUCCEEDED, $withdrawal->fresh()->status);
        $this->assertSame('0.00', $wallet->fresh()->locked_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'reference_type' => Withdrawal::class,
            'reference_id' => $withdrawal->id,
            'idempotency_key' => 'withdrawal:'.$withdrawal->id.':succeeded:locked',
            'balance_bucket' => 'locked',
        ]);
        $this->assertDatabaseHas('withdrawal_status_histories', [
            'withdrawal_id' => $withdrawal->id,
            'to_status' => WithdrawalStatus::SUCCEEDED->value,
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.withdrawal.processing_started',
            'subject_id' => $withdrawal->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.withdrawal.succeeded',
            'subject_id' => $withdrawal->id,
        ]);
    }

    public function test_admin_rejection_releases_reservation_and_is_audited(): void
    {
        [$professional, $withdrawal, $wallet] = $this->createWithdrawal();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.reject', $withdrawal), [
                'reason' => 'La destination ne correspond pas aux informations vérifiées.',
            ])
            ->assertRedirect(route('admin.withdrawals.show', $withdrawal));

        $this->assertSame(WithdrawalStatus::REJECTED, $withdrawal->fresh()->status);
        $this->assertSame('100.00', $wallet->fresh()->available_balance);
        $this->assertSame('0.00', $wallet->fresh()->locked_balance);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.withdrawal.rejected',
            'subject_id' => $withdrawal->id,
        ]);
        $this->assertDatabaseCount('withdrawal_status_histories', 2);
    }

    public function test_financial_detail_access_is_audited_without_copying_destination_into_audit_metadata(): void
    {
        [, $withdrawal] = $this->createWithdrawal();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.show', $withdrawal))
            ->assertOk()
            ->assertSee('Destination complète')
            ->assertSee('243970123456');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.withdrawal.destination.viewed',
            'subject_id' => $withdrawal->id,
        ]);

        $log = \App\Models\AuditLog::query()
            ->where('action', 'admin.withdrawal.destination.viewed')
            ->firstOrFail();

        $this->assertStringNotContainsString('243970123456', json_encode($log->metadata, JSON_THROW_ON_ERROR));
    }

    private function createWithdrawal(): array
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $professional->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();

        $withdrawal = app(WithdrawalService::class)->request(
            professional: $profile,
            amount: '30.00',
            currency: 'USD',
            provider: 'mobile_money',
            destination: '243970123456',
            idempotencyKey: 'admin-withdrawal-'.uniqid('', true),
        );

        return [$professional, $withdrawal, $wallet];
    }
}
