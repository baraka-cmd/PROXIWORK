<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Service;
use App\Payments\Gateways\FakePaymentGateway;
use App\Services\Settings\PlatformSettingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PaymentGateway::class,
            FakePaymentGateway::class
        );
    }

    public function boot(): void
    {
        // Worker-loop heartbeat; stale records are ignored after two minutes.
        Queue::looping(function (): void {
            static $lastHeartbeatAt = 0;

            if (time() - $lastHeartbeatAt < 20) {
                return;
            }

            $lastHeartbeatAt = time();

            try {
                $hostname = gethostname() ?: 'unknown-host';
                $pid = getmypid() ?: 0;
                $workerId = hash('sha256', $hostname.'|'.$pid);
                $now = now();

                DB::table('queue_worker_heartbeats')->upsert(
                    [[
                        'worker_id' => $workerId,
                        'hostname' => mb_substr($hostname, 0, 191),
                        'process_id' => $pid,
                        'last_seen_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['worker_id'],
                    ['hostname', 'process_id', 'last_seen_at', 'updated_at'],
                );
            } catch (\Throwable) {
                // Monitoring must never prevent a worker from processing jobs.
            }
        });

        /*
         * Relations polymorphes.
         *
         * Permet à Laravel de résoudre l'alias "service"
         * vers le modèle App\Models\Service.
         */
        Relation::morphMap([
            'service' => Service::class,
        ]);

        /*
         * Limitation générale des requêtes API.
         */
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });

        /*
         * Limitation des tentatives de connexion.
         */
        RateLimiter::for('auth-login', function (Request $request) {
            return [
                Limit::perMinute(30)->by($request->ip()),
                Limit::perMinute(5)->by(
                    sprintf('email:%s', mb_strtolower((string) $request->input('email')))
                ),
            ];
        });

        /*
         * Limitation des tentatives de connexion administrateur.
         */
        RateLimiter::for('admin-login', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by(
                    sprintf('admin-email:%s', mb_strtolower((string) $request->input('email')))
                ),
            ];
        });

        /*
         * Limitation des opérations sensibles.
         */
        RateLimiter::for('auth-sensitive', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by(
                    sprintf('sensitive-user:%s', $request->user()?->getAuthIdentifier() ?? 'guest')
                ),
                Limit::perMinute(5)->by(
                    sprintf('sensitive-email:%s', mb_strtolower((string) $request->input('email')))
                ),
            ];
        });

        /*
         * Limitation de l'envoi de messages.
         */
        RateLimiter::for('message-send', function (Request $request) {
            $conversationKey = (string) $request->route('conversation');

            return [
                Limit::perMinute(30)->by(
                    sprintf('message-user:%s', $request->user()?->getAuthIdentifier() ?? $request->ip())
                ),
                Limit::perMinute(10)->by(
                    sprintf('message-conversation:%s', $conversationKey)
                ),
            ];
        });

        /*
         * Limitation des opérations de paiement.
         */
        RateLimiter::for('payment', function (Request $request) {
            $userKey = sprintf('payment-user:%s', $request->user()?->getAuthIdentifier() ?? $request->ip());

            $routeResource = $request->route('order') ?? $request->route('payment');
            if (is_object($routeResource) && method_exists($routeResource, 'getKey')) {
                $routeResource = $routeResource->getKey();
            }
            $orderKey = sprintf('payment-order:%s', (string) ($routeResource ?? $request->ip()));

            return [
                Limit::perMinute(10)->by($userKey),
                Limit::perMinute(5)->by($orderKey),
            ];
        });

        /*
         * Le compteur partagé du menu est calculé côté serveur, sur les seules
         * notifications de l'utilisateur authentifié, hors archives.
         */
        View::composer('layouts.app', function ($view): void {
            $view->with('platformName', app(PlatformSettingService::class)->get('platform.name'));
        });

        View::composer('layouts.client', function ($view): void {
            $user = auth()->user();

            $view->with(
                'clientUnreadNotificationCount',
                $user?->unreadNotifications()->whereNull('archived_at')->count() ?? 0,
            );
        });
    }
}
