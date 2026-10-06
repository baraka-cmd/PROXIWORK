<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
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
});
