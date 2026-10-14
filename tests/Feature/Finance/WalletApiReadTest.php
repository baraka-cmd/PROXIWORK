<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletApiReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_api_wallet_read_does_not_create_a_wallet_when_none_exists(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/professional/wallet/')
            ->assertNotFound();

        $this->assertDatabaseCount('wallets', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_api_wallet_read_returns_only_the_existing_wallet_snapshot(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->id]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill([
            'available_balance' => '123.45',
            'pending_balance' => '6.70',
            'locked_balance' => '8.90',
        ])->save();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/professional/wallet/?currency=USD')
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.available_balance', '123.45')
            ->assertJsonPath('data.pending_balance', '6.70')
            ->assertJsonPath('data.locked_balance', '8.90');

        $wallet->refresh();

        $this->assertSame('123.45', $wallet->available_balance);
        $this->assertSame('6.70', $wallet->pending_balance);
        $this->assertSame('8.90', $wallet->locked_balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_api_wallet_read_cannot_access_another_professionals_wallet(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('professional');
        $ownerProfile = ProfessionalProfile::factory()->create(['user_id' => $owner->id]);
        app(WalletService::class)->getOrCreate($ownerProfile, 'USD');

        $other = User::factory()->create();
        $other->assignRole('professional');
        ProfessionalProfile::factory()->create(['user_id' => $other->id]);

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/v1/professional/wallet/?currency=USD')
            ->assertNotFound();
    }
}
