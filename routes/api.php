<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Rbac\RoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-sensitive');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-sensitive');
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:auth-sensitive');
            Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:auth-sensitive');
        });
    });

    Route::middleware(['security.headers', 'auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);

        Route::apiResource('addresses', AddressController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('addresses/{address}/default', [AddressController::class, 'setDefault']);

        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
            Route::get('preferences', [NotificationController::class, 'preferences']);
            Route::patch('preferences', [NotificationController::class, 'updatePreferences']);
        });
    });

    Route::middleware(['security.headers', 'auth:sanctum', 'throttle:api'])->prefix('audit')->group(function (): void {
        Route::get('logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');
        Route::get('logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit.view');
    });

    Route::middleware(['security.headers', 'auth:sanctum', 'throttle:api'])->prefix('rbac')->group(function (): void {
        Route::get('me', function (Request $request) {
            $user = $request->user()->load(['roles.permissions']);

            return response()->json([
                'message' => 'Autorisations effectives récupérées avec succès.',
                'data' => [
                    'roles' => $user->roles->pluck('name')->values(),
                    'permissions' => $user->roles
                        ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                        ->unique()
                        ->values(),
                ],
                'meta' => [],
            ]);
        });

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:rbac.view');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:rbac.view');
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:rbac.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:rbac.manage');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:rbac.manage');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:rbac.manage');
    });
});
