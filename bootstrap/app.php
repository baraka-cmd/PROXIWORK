<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureSessionVersionIsCurrent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AddSecurityHeaders::class);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'security.headers' => AddSecurityHeaders::class,
            'active.account' => EnsureUserIsActive::class,
            'session.version' => EnsureSessionVersionIsCurrent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $isApi = static fn (Request $request): bool => $request->is('api/*');

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return response()->json([
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return response()->json([
                'message' => 'Authentification échouée ou requise.',
                'errors' => [],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return response()->json([
                'message' => 'Action non autorisée.',
                'errors' => [],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return response()->json([
                'message' => 'Ressource introuvable.',
                'errors' => [],
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            $status = $e->getStatusCode();

            if (in_array($status, [400, 401, 403, 404, 405, 409, 429], true)) {
                return response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Requête impossible.',
                    'errors' => [],
                ], $status);
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            return response()->json([
                'message' => 'Une erreur interne est survenue.',
                'errors' => [],
            ], 500);
        });
    })
    ->create();
