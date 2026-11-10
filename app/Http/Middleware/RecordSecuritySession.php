<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SecuritySession;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class RecordSecuritySession
{
    public function handle(\Illuminate\Http\Request $request, \Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user() ?? Auth::guard('web')->user();

        if ($user !== null) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $identifier = 'sanctum:'.$token->getKey();
                $guard = 'sanctum';
            } elseif ($request->hasSession() && Auth::guard('web')->check()) {
                $identifier = 'web:'.$request->session()->getId();
                $guard = 'web';
            } else {
                $identifier = null;
                $guard = null;
            }

            if ($identifier !== null && $guard !== null) {
                $hash = hash_hmac('sha256', $identifier, (string) config('app.key'));
                $revoked = SecuritySession::query()
                    ->where('guard', $guard)
                    ->where('identifier_hash', $hash)
                    ->whereNotNull('revoked_at')
                    ->exists();

                if ($revoked) {
                    if ($guard === 'sanctum' && $token instanceof PersonalAccessToken) {
                        $token->delete();
                    } elseif ($request->hasSession()) {
                        Auth::guard('web')->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    }

                    abort(401, 'Cette session a été révoquée. Reconnectez-vous.');
                }
            }
        }

        $response = $next($request);

        app(\App\Services\Audit\SecuritySessionService::class)->touch($request);

        return $response;
    }
}
