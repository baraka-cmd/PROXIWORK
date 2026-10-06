<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });

        RateLimiter::for('auth-login', function (Request $request) {
            return [
                Limit::perMinute(30)->by($request->ip()),
                Limit::perMinute(5)->by(
                    'email:'.mb_strtolower((string) $request->input('email'))
                ),
            ];
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by(
                    'admin-email:'.mb_strtolower((string) $request->input('email'))
                ),
            ];
        });

        RateLimiter::for('auth-sensitive', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }
}
