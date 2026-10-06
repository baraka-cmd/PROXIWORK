<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
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

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);

        Route::apiResource('addresses', AddressController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
    });

    Route::middleware(['auth:sanctum'])->prefix('rbac')->group(function (): void {
        Route::get('me', function (Request $request) {
            $user = $request->user()->load(['roles.permissions']);

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $user->roles->pluck('name')->values(),
                    'permissions' => $user->roles
                        ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                        ->unique()
                        ->values(),
                ],
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
