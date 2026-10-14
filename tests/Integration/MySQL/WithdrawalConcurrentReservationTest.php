<?php

declare(strict_types=1);

namespace Tests\Integration\MySQL;

use App\Exceptions\PaymentConflictException;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class WithdrawalConcurrentReservationTest extends TestCase
{
    public function test_two_concurrent_requests_cannot_reserve_more_than_the_available_balance(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'Ce test de concurrence doit être exécuté contre MySQL/InnoDB.');

        $this->assertTrue(
            function_exists('pcntl_fork') && function_exists('stream_socket_pair'),
            'Le runner MySQL doit prendre en charge pcntl_fork et les sockets Unix pour tester deux demandes réellement concurrentes.',
        );

        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('professional');
        $profile = ProfessionalProfile::factory()->create(['user_id' => $user->getKey()]);
        $wallet = app(WalletService::class)->getOrCreate($profile, 'USD');
        $wallet->forceFill(['available_balance' => '100.00'])->save();

        $children = [];

        foreach ([
            'concurrent-withdrawal-request-0001',
            'concurrent-withdrawal-request-0002',
        ] as $idempotencyKey) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $this->assertIsArray($sockets, 'Impossible de créer le canal de synchronisation du test.');

            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Impossible de créer un processus pour le test de concurrence.');
            }

            if ($pid === 0) {
                fclose($sockets[0]);

                // Each child must use its own MySQL connection after the fork.
                DB::purge();
                DB::reconnect();

                fread($sockets[1], 1);

                try {
                    $childProfile = ProfessionalProfile::query()->findOrFail($profile->getKey());
                    app(WithdrawalService::class)->request(
                        $childProfile,
                        '70.00',
                        'USD',
                        'mobile_money',
                        '243970123456',
                        $idempotencyKey,
                    );
                    fwrite($sockets[1], 'created');
                } catch (PaymentConflictException) {
                    fwrite($sockets[1], 'rejected');
                } catch (Throwable $exception) {
                    fwrite($sockets[1], 'error:'.get_class($exception).':'.$exception->getMessage());
                }

                fclose($sockets[1]);
                exit(0);
            }

            fclose($sockets[1]);
            $children[] = ['pid' => $pid, 'socket' => $sockets[0]];
        }

        // Release both children together so their reservation attempts overlap.
        foreach ($children as $child) {
            fwrite($child['socket'], 'G');
            fflush($child['socket']);
        }

        $outcomes = [];

        foreach ($children as $child) {
            $outcomes[] = stream_get_contents($child['socket']);
            fclose($child['socket']);
            pcntl_waitpid($child['pid'], $status);
        }

        $this->assertEqualsCanonicalizing(['created', 'rejected'], $outcomes);

        // Reconnect after the children exit; the parent must not reuse a PDO
        // handle inherited across fork boundaries.
        DB::purge();
        DB::reconnect();

        $freshWallet = Wallet::query()->findOrFail($wallet->getKey());

        $this->assertSame('30.00', $freshWallet->available_balance);
        $this->assertSame('70.00', $freshWallet->locked_balance);
        $this->assertSame(1, DB::table('withdrawals')->where('professional_id', $profile->getKey())->count());
        $this->assertSame(2, DB::table('wallet_transactions')->where('wallet_id', $wallet->getKey())->count());
    }
}
