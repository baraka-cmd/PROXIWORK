<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\WithdrawalStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WithdrawalStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    public function test_each_significant_status_transition_persists_a_database_notification_once(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->getKey()]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();

        $service = app(WithdrawalService::class);
        $withdrawal = $service->request(
            $profile,
            '25.00',
            'USD',
            'mobile_money',
            '243970123456',
            'withdraw-notification-test-0001',
        );

        $service->markProcessing($withdrawal, $user->getKey(), 'Le traitement manuel a commencé.');
        $service->markReviewRequired($withdrawal, 'La confirmation externe est encore ambiguë.', $user->getKey());
        $service->markFailed(
            $withdrawal,
            'PROVIDER_CONFIRMED_FAILURE',
            'Le résultat négatif a été confirmé par la vérification manuelle.',
            $user->getKey(),
        );
        // Repeated delivery of the same terminal operation must not notify twice.
        $service->markFailed(
            $withdrawal,
            'PROVIDER_CONFIRMED_FAILURE',
            'Le résultat négatif a été confirmé par la vérification manuelle.',
            $user->getKey(),
        );

        $notifications = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->getKey())
            ->get();

        $this->assertCount(4, $notifications);

        $payloads = $notifications->map(
            static fn (object $row): array => json_decode($row->data, true, 512, JSON_THROW_ON_ERROR),
        );

        $this->assertEqualsCanonicalizing(
            [
                WithdrawalStatus::REQUESTED->value,
                WithdrawalStatus::PROCESSING->value,
                WithdrawalStatus::REVIEW_REQUIRED->value,
                WithdrawalStatus::FAILED->value,
            ],
            $payloads->pluck('status')->all(),
        );

        $this->assertTrue($payloads->every(
            static fn (array $payload): bool => $payload['kind'] === 'withdrawal_status_changed'
                && $payload['withdrawal_id'] === $withdrawal->getKey()
                && $payload['url'] === route('professional.withdrawals.show', $withdrawal),
        ));
    }
}
