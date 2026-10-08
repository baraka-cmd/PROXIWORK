<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\RbacDashboardController;
use App\Http\Controllers\Web\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    return response()->json(['success' => true, 'message' => 'Password reset link received.', 'data' => ['token' => $token, 'email' => $request->query('email')]]); 
})->middleware('guest')->name('password.reset');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:admin.dashboard.view')->name('dashboard');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::middleware('permission:rbac.view')->group(function (): void {
            Route::get('rbac', [RbacDashboardController::class, 'index'])->name('rbac.dashboard');
        });
    });
});
