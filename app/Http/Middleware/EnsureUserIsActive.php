<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserAccountStatus;
use App\Services\Auth\SessionRevocationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->account_status !== UserAccountStatus::ACTIVE) {
            app(SessionRevocationService::class)->revokeAll($user);

            if ($request->hasSession() && Auth::guard('web')->id() === $user->getAuthIdentifier()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(403, 'Compte suspendu.');
        }

        return $next($request);
    }
}
