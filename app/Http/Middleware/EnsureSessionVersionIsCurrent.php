<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionVersionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user === null || ! $request->hasSession()) {
            return $next($request);
        }

        $currentVersion = (int) $user->session_version;
        $sessionVersion = $request->session()->get('auth.session_version');

        if ($sessionVersion === null && $currentVersion === 0) {
            $request->session()->put('auth.session_version', 0);

            return $next($request);
        }

        if ($sessionVersion === null || (int) $sessionVersion !== $currentVersion) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException('Votre session a expiré. Veuillez vous reconnecter.');
        }

        return $next($request);
    }
}
