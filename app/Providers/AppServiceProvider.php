<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Models\Service;
use App\Payments\Gateways\FakePaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
                    'email:' . mb_strtolower(
                        (string) $request->input('email')
                    )
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
                    'admin-email:' . mb_strtolower(
                        (string) $request->input('email')
                    )
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
                    'sensitive-user:' .
                    ($request->user()?->getAuthIdentifier() ?? 'guest')
                ),
                Limit::perMinute(5)->by(
                    'sensitive-email:' . mb_strtolower(
                        (string) $request->input('email')
                    )
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
                    'message-user:' .
                    ($request->user()?->getAuthIdentifier() ?? $request->ip())
                ),
                Limit::perMinute(10)->by(
                    'message-conversation:' . $conversationKey
                ),
            ];
        });

        /*
         * Limitation des opérations de paiement.
         */
        RateLimiter::for('payment', function (Request $request) {
            $userKey = 'payment-user:' .
                ($request->user()?->getAuthIdentifier() ?? $request->ip());

            $orderKey = 'payment-order:' .
                ((string) $request->route('order'));

            return [
                Limit::perMinute(10)->by($userKey),
                Limit::perMinute(5)->by($orderKey),
            ];
        });
    }
}