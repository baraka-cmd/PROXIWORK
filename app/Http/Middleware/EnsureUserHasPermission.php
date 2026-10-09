<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless(
            $user && collect($permissions)->contains(fn (string $permission) => $user->hasPermissionTo($permission)),
            403,
            'You do not have the required permission.',
        );

        return $next($request);
    }
}
