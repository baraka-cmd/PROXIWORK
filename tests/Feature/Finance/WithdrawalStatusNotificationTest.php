<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\WithdrawalStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Notifications\WithdrawalStatusChanged;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WithdrawalStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Notification::fake();
    }

    public function test_each_significant_status_transition_notifies_the_professional(): void
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

        Notification::assertSentTo(
            $user,
            WithdrawalStatusChanged::class,
            static fn (WithdrawalStatusChanged $notification): bool => $notification->status === WithdrawalStatus::REQUESTED
                && $notification->withdrawalId === $withdrawal->getKey(),
        );

        $service->markProcessing($withdrawal, $user->getKey(), 'Le traitement manuel a commencé.');
        Notification::assertSentTo(
            $user,
            WithdrawalStatusChanged::class,
            static fn (WithdrawalStatusChanged $notification): bool => $notification->status === WithdrawalStatus::PROCESSING,
        );

        $service->markReviewRequired($withdrawal, 'La confirmation externe est encore ambiguë.', $user->getKey());
        Notification::assertSentTo(
            $user,
            WithdrawalStatusChanged::class,
            static fn (WithdrawalStatusChanged $notification): bool => $notification->status === WithdrawalStatus::REVIEW_REQUIRED,
        );

        $service->markFailed(
            $withdrawal,
            'PROVIDER_CONFIRMED_FAILURE',
            'Le résultat négatif a été confirmé par la vérification manuelle.',
            $user->getKey(),
        );
        Notification::assertSentTo(
            $user,
            WithdrawalStatusChanged::class,
            static fn (WithdrawalStatusChanged $notification): bool => $notification->status === WithdrawalStatus::FAILED,
        );

        Notification::assertSentToTimes($user, WithdrawalStatusChanged::class, 4);
    }
}
