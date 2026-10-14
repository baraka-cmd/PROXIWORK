<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WithdrawalProviderReferenceUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_database_rejects_reusing_an_external_reference_for_another_withdrawal(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->getKey()]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();

        $service = app(WithdrawalService::class);
        $first = $service->request(
            $profile,
            '20.00',
            'USD',
            'mobile_money',
            '243970111111',
            'provider-reference-first-0001',
        );
        $second = $service->request(
            $profile,
            '20.00',
            'USD',
            'mobile_money',
            '243970222222',
            'provider-reference-second-0001',
        );

        $service->markProcessing($first);
        $service->markSucceeded($first, 'EXTERNAL-REFERENCE-UNIQUE-0001');

        $this->expectException(QueryException::class);

        // Deliberately bypass the service to prove the database constraint is
        // the final protection against a race between two settlements.
        DB::table('withdrawals')
            ->where('id', $second->getKey())
            ->update(['provider_transaction_id' => 'EXTERNAL-REFERENCE-UNIQUE-0001']);
    }
}
