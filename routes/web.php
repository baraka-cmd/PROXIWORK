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
        Route::get('profile', [\App\Http\Controllers\Web\Client\ProfileController::class, 'show'])->name('profile');
        Route::get('profile/edit', [\App\Http\Controllers\Web\Client\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [\App\Http\Controllers\Web\Client\ProfileController::class, 'update'])->name('profile.update');

        Route::get('addresses', [\App\Http\Controllers\Web\Client\AddressController::class, 'index'])->name('addresses.index');
        Route::get('addresses/create', [\App\Http\Controllers\Web\Client\AddressController::class, 'create'])->name('addresses.create');
        Route::post('addresses', [\App\Http\Controllers\Web\Client\AddressController::class, 'store'])->name('addresses.store');
        Route::get('addresses/{address}/edit', [\App\Http\Controllers\Web\Client\AddressController::class, 'edit'])->name('addresses.edit');
        Route::patch('addresses/{address}', [\App\Http\Controllers\Web\Client\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('addresses/{address}', [\App\Http\Controllers\Web\Client\AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::post('addresses/{address}/default', [\App\Http\Controllers\Web\Client\AddressController::class, 'makeDefault'])->name('addresses.default');
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
