<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\RegisterController;
use App\Http\Controllers\Web\RbacDashboardController;
use App\Http\Controllers\Web\Client\OrderController;
use App\Http\Controllers\Web\Client\PaymentController;
use App\Http\Controllers\Web\Professional\WalletController as ProfessionalWalletController;
use App\Http\Controllers\Web\Professional\WithdrawalController as ProfessionalWithdrawalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('client')->name('client.')->middleware(['active.account', 'role:client'])->group(function (): void {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}/payment', [OrderController::class, 'pay'])->name('orders.payment');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/payment', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });

    Route::prefix('professional')->name('professional.')
        ->middleware(['active.account', 'role:professional'])
        ->group(function (): void {
            Route::get('wallet', [ProfessionalWalletController::class, 'index'])->name('wallet');

            Route::get('withdrawals', [ProfessionalWithdrawalController::class, 'index'])->name('withdrawals');
            Route::get('withdrawals/create', [ProfessionalWithdrawalController::class, 'create'])->name('withdrawals.create');
            Route::post('withdrawals', [ProfessionalWithdrawalController::class, 'store'])
                ->middleware('throttle:payment')
                ->name('withdrawals.store');
            Route::get('withdrawals/{withdrawal}', [ProfessionalWithdrawalController::class, 'show'])->name('withdrawals.show');
        });
});

Route::get('/reset-password/{token}', function (Request $request, string $token) {
    return response()->json([
        'success' => true,
        'message' => 'Password reset link received.',
        'data' => ['token' => $token, 'email' => $request->query('email')],
    ]);
})->middleware('guest')->name('password.reset');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['auth', 'permission:rbac.view'])->group(function (): void {
        Route::get('rbac', [RbacDashboardController::class, 'index'])->name('rbac.dashboard');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
    });
});
