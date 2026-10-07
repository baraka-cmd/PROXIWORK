<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\RbacDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('client')->name('client.')->middleware(['active.account', 'role:client'])->group(function (): void {
        Route::get('notifications', [\App\Http\Controllers\Web\Client\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [\App\Http\Controllers\Web\Client\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('notifications/read-all', [\App\Http\Controllers\Web\Client\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

        Route::get('messages', [\App\Http\Controllers\Web\Client\MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{conversation}', [\App\Http\Controllers\Web\Client\MessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{conversation}', [\App\Http\Controllers\Web\Client\MessageController::class, 'store'])->name('messages.store');
        Route::post('messages/{conversation}/read', [\App\Http\Controllers\Web\Client\MessageController::class, 'read'])->name('messages.read');
    });
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    return response()->json([
        'success' => true,
        'message' => 'Password reset link received.',
        'data' => [
            'token' => $token,
            'email' => $request->query('email'),
        ],
    ]);
})->middleware('guest')->name('password.reset');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
        Route::get('rbac', [RbacDashboardController::class, 'index'])->name('rbac.dashboard');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
    });
});
