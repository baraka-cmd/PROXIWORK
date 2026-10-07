<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserAccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->account_status !== UserAccountStatus::ACTIVE) {
            abort(403, 'Compte suspendu.');
        }

        return $next($request);
    }
}
