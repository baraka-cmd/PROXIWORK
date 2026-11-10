<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restore the session guard before the web middleware stack executes.
 *
 * Stateful API authentication can switch AuthManager's default guard during
 * a mixed request sequence. AuthenticateSession calls viaRemember() on the
 * default guard, which is not supported by Sanctum's RequestGuard.
 */
class EnsureWebGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('web');

        return $next($request);
    }
}
